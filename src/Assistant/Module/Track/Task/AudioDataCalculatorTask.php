<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\GetId3\Exception\GetId3Exception;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierProcessException;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Track\Extension\TrackService;
use KeyTools\KeyTools;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task mający za zadanie obliczenie tonacji oraz liczby uderzeń na minutę
 * w zadanym pliku (utworze muzycznym)
 */
final class AudioDataCalculatorTask extends AbstractTask
{
    protected static $defaultName = 'track:calculate-audio-data';

    private array $stats;

    public function __construct(
        Logger $logger,
        private MusicClassifierService $musicClassifierService,
        private Id3Adapter $id3,
        private TrackService $trackService,
        private KeyTools $keyTools,
        private array $parameters,
    ) {
        parent::__construct($logger);

        $this->stats = [
            'processed' => 0,
            'updated' => 0,
            'mismatch' => [ 'initial_key' => 0, 'bpm' => 0 ],
            'skipped' => [ 'too_long' => 0, 'already_calculated' => 0, 'same_data' => 0 ],
            'error' => [ 'classifier' => 0, 'tags' => 0, 'other' => 0 ],
        ];
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(MusicClassifierService::class),
            $container->get(Id3Adapter::class),
            $container->get(TrackService::class),
            KeyTools::fromNotation(KeyTools::NOTATION_MUSICAL_ESSENTIA),
            $container->get(Config::class)->get('collection'),
        );
    }

    protected function configure(): void
    {
        $collectionRootDir = $this->parameters['root_dir'];

        $this
            ->setDescription('Calculates BPM and initial key for track(s)')
            ->addArgument(
                'pathname',
                InputArgument::OPTIONAL,
                'Pathname to search tracks to calculate BPM and initial key',
                $collectionRootDir
            )->addOption(
                'skip-calculated',
                's',
                InputOption::VALUE_NONE,
                'Skip tracks with calculated BPM and initial key field'
            )->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Process directories recursively')
            ->addOption('write-data', 'w', InputOption::VALUE_NONE, 'Write BPM and initial key to track metadata');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Task executed', self::getInputParams($input));

        $skipCalculated = $input->getOption('skip-calculated');
        $writeData = $input->getOption('write-data');

        $files = $this->getFiles(
            $input->getArgument('pathname'),
            $input->getOption('recursive')
        );

        foreach ($files as $file) {
            $this->logger->debug('Processing track', [ 'pathname' => $file->getPathname() ]);

            $this->stats['processed']++;

            $track = $this->trackService->createFromFile($file->getPathname());

            try {
                $metadata = $this->id3
                    ->setFile($track->getFile())
                    ->readId3v2Metadata();

                if ($this->id3->getTrackLength() / 60 > 20) {
                    $this->stats['skipped']['too_long']++;

                    $this->logger->debug(
                        'Track is too long, skipping...',
                        [ 'length' => $this->id3->getTrackLength() / 60 ]
                    );

                    unset($file, $track, $metadata);

                    continue;
                }

                $hasInitialKey = isset($metadata['initial_key']) === true;
                $hasBpm = isset($metadata['bpm']) === true;

                if ($skipCalculated === true && $hasInitialKey === true && $hasBpm === true) {
                    $this->stats['skipped']['already_calculated']++;

                    $this->logger->debug(
                        'Track is already calculated (bpm and initial_key exists), skipping',
                        [ 'bpm' => $metadata['bpm'], 'initial_key' => $metadata['initial_key'] ]
                    );

                    unset($file, $track, $metadata);

                    continue;
                }

                $classificationResult = $this->musicClassifierService->analyze($track->getFile());
                $audioData = $this->getAudioData($classificationResult);

                if ($this->isTrackHasSameData($metadata, $audioData) === true) {
                    $this->stats['skipped']['same_data']++;

                    $this->logger->debug('Track has the same audio data, update is not necessary', [
                        'initial_key' => $metadata['initial_key'],
                        'bpm' => $metadata['bpm'],
                        'classificationResultFilename' => $classificationResult->getFile()?->getBasename(),
                    ]);

                    unset($file, $track, $metadata, $classificationResult, $audioData);

                    continue;
                }

                if ($hasInitialKey === true && $metadata['initial_key'] !== $audioData['initial_key']) {
                    $this->stats['mismatch']['initial_key']++;
                }
                if ($hasBpm === true && $metadata['bpm'] !== $audioData['bpm']) {
                    $this->stats['mismatch']['bpm']++;
                }

                $this->logger->debug(sprintf('%s track audio data', $writeData ? 'Updating' : 'Calculated'), [
                    'audioData' => $audioData,
                    'metadata' => [
                        'initial_key' => $hasInitialKey === true ? $metadata['initial_key'] : null,
                        'bpm' => $hasBpm === true ? $metadata['bpm'] : null,
                    ],
                    'classificationResultFilename' => $classificationResult->getFile()?->getBasename(),
                ]);

                if ($writeData === true) {
                    $this->id3->writeId3v2Metadata($audioData);

                    if ($this->id3->getWriterWarnings()) {
                        $this->logger->warning('Track metadata saved with warnings', $this->id3->getWriterWarnings());
                    }

                    $this->stats['updated']++;
                }

                $this->logger->debug('Track processing completed successfully');
            } catch (MusicClassifierProcessException $e) {
                $this->stats['error']['classifier']++;

                $this->logger->error($e->getMessage(), [
                    'pathname' => $track->getPathname(),
                    'metadata' => $metadata ?? null,
                    'commandLine' => $e->getProcessCommandLine(),
                ]);
            } catch (GetId3Exception $e) {
                $this->stats['error']['tags']++;

                $this->logger->error($e->getMessage(), [
                    'pathname' => $track->getPathname(),
                    'metadata' => $metadata ?? null,
                    'audioData' => $audioData ?? null,
                    'id3WriterErrors' => $this->id3->getWriterErrors(),
                    'id3WriterWarnings' => $this->id3->getWriterWarnings(),
                ]);
            } catch (\Exception $e) {
                $this->stats['error']['other']++;

                $this->logger->critical($e->getMessage(), [
                    'pathname' => $track->getPathname(),
                    'metadata' => $metadata ?? null,
                    'audioData' => $audioData ?? null,
                    'exception' => $e,
                ]);
            } finally {
                unset($file, $track, $metadata, $classificationResult, $audioData);
            }
        }

        $this->logger->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /**
     * Określa, czy dane zawarte w metadanych pliku są takie same jak te obliczone przez music classifier service
     *
     * @param array|null $metadata
     * @param array $audioData
     * @return bool
     */
    private function isTrackHasSameData(?array $metadata, array $audioData): bool
    {
        if (isset($metadata['bpm']) === false || isset($metadata['initial_key']) === false) {
            return false;
        }

        $hasSameBpm = (float) $metadata['bpm'] === (float) $audioData['bpm'];
        $hasSameInitialKey = $metadata['initial_key'] === $audioData['initial_key'];

        return $hasSameBpm && $hasSameInitialKey;
    }

    /**
     * @param string $pathname
     * @param bool $recursive
     * @return SplFileInfo[]|Finder
     */
    private function getFiles(string $pathname, bool $recursive): array|Finder
    {
        return Finder::create([
            'pathname' => $pathname,
            'mode' => Finder::MODE_FILES_ONLY,
            'recursive' => $recursive,
        ]);
    }

    private function getAudioData(MusicClassifierResult $result): array
    {
        $bpm = $result->getBpm();
        $key = $result->getMusicalKey();

        $audioData = [
            'bpm' => $bpm,
            'initial_key' => $this->keyTools->convertKeyToNotation($key, KeyTools::NOTATION_CAMELOT_KEY),
        ];

        return $audioData;
    }
}
