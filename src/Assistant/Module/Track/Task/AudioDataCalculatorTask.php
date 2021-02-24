<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Extension\Backend\Exception\AudioDataCalculatorException;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\GetId3\Exception\WriterException;
use Assistant\Module\Common\Task\AbstractTask;
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
    private Id3Adapter $id3;

    private BackendClient $backend;

    private array $stats;

    protected function configure(): void
    {
        $collectionRootDir = $this->app->container['parameters']['collection']['root_dir'];

        $this
            ->setName('track:calculate-audio-data')
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

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->id3 = new Id3Adapter();
        $this->backend = new BackendClient();

        $this->stats = [
            'processed' => 0,
            'updated' => 0,
            'mismatch' => [ 'initial_key' => 0, 'bpm' => 0 ],
            'skipped' => [ 'too_long' => 0,  'already_calculated' => 0, 'same_data' => 0 ],
            'error' => [ 'backend' => 0, 'tags' => 0, 'other' => 0 ],
        ];
    }

    /**
     * Rozpoczyna proces indeksowania kolekcji
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->log->info('Task executed', array_merge($input->getArguments(), $input->getOptions()));

        $skipCalculated = $input->getOption('skip-calculated');
        $writeData = $input->getOption('write-data');

        $files = $this->getFiles(
            $input->getArgument('pathname'),
            $input->getOption('recursive')
        );

        foreach ($files as $file) {
            $this->app->log->debug('Processing track', [ 'pathname' => $file->getPathname() ]);

            $this->stats['processed']++;

            // @todo: użyć klasy TrackBuilder, poniżej korzystać już tylko z modelu Track
            //        $track = ($this->app->container[TrackBuilder::class])->fromFile($file->getPathname());

            try {
                $metadata = $this->id3
                    ->setFile($file)
                    ->readId3v2Metadata();

                if ($this->id3->getTrackLength() / 60 > 20) {
                    $this->stats['skipped']['too_long']++;

                    $this->app->log->debug(
                        'Track is too long, skipping...',
                        [ 'length' => $this->id3->getTrackLength() / 60 ]
                    );

                    unset($file, $metadata);

                    continue;
                }

                $hasInitialKey = isset($metadata['initial_key']) === true;
                $hasBpm = isset($metadata['bpm']) === true;

                if ($skipCalculated === true && $hasInitialKey === true && $hasBpm === true) {
                    $this->stats['skipped']['already_calculated']++;

                    $this->app->log->debug(
                        'Track is already calculated (bpm and initial_key exists), skipping',
                        [ 'bpm' => $metadata['bpm'], 'initial_key' => $metadata['initial_key'] ]
                    );

                    unset($file, $metadata);

                    continue;
                }

                $audioData = $this->backend->calculateAudioData($file);

                if ($this->isTrackHasSameData($metadata, $audioData) === true) {
                    $this->stats['skipped']['same_data']++;

                    $this->app->log->debug('Track has the same audio data, update is not necessary', $audioData);

                    unset($file, $metadata, $audioData);

                    continue;
                }

                if ($hasInitialKey === true && $metadata['initial_key'] !== $audioData['initial_key']) {
                    $this->stats['mismatch']['initial_key']++;
                }
                if ($hasBpm === true && $metadata['bpm'] !== $audioData['bpm']) {
                    $this->stats['mismatch']['bpm']++;
                }

                $this->app->log->debug(
                    sprintf('%s track audio data', ($writeData === true) ? 'Updating' : 'Calculated'),
                    [
                        'audioData' => $audioData,
                        'metadata' => [
                            'initial_key' => $hasInitialKey === true ? $metadata['initial_key'] : null,
                            'bpm' => $hasBpm === true ? $metadata['bpm'] : null,
                        ],
                    ]
                );

                if ($writeData === true) {
                    $this->id3->writeId3v2Metadata($audioData);

                    if ($this->id3->getWriterWarnings()) {
                        $this->app->log->warning('Track metadata saved with warnings', $this->id3->getWriterWarnings());
                    }

                    $this->stats['updated']++;
                }

                $this->app->log->debug('Track processing completed successfully');
            } catch (AudioDataCalculatorException $e) {
                $this->stats['error']['backend']++;

                $this->app->log->error(
                    $e->getMessage(),
                    [ 'pathname' => $file->getPathname(), 'metadata' => $metadata ]
                );
            } catch (WriterException $e) {
                $this->stats['error']['tags']++;

                $this->app->log->error(
                    $e->getMessage(),
                    [
                        'pathname' => $file->getPathname(),
                        'metadata' => $metadata,
                        'audioData' => $audioData,
                        'id3WriterErrors' => $this->id3->getWriterErrors(),
                        'id3WriterWarnings' => $this->id3->getWriterWarnings(),
                    ]
                );
            } catch (\Exception $e) {
                $this->stats['error']['other']++;

                $this->app->log->critical(
                    $e->getMessage(),
                    [
                        'pathname' => $file->getPathname(),
                        'metadata' => $metadata,
                        'audioData' => $audioData,
                        'exception' => $e,
                    ]
                );
            } finally {
                unset($file, $metadata, $audioData);
            }
        }

        $this->app->log->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /**
     * Określa, czy dane zawarte w metadanych pliku są takie same jak te obliczone przez backend
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

        return (string) $metadata['bpm'] === (string) $audioData['bpm'] && $metadata['initial_key'] === $audioData['initial_key'];
    }

    /**
     * @param string $pathname
     * @param bool $recursive
     * @return Finder|SplFileInfo[]
     */
    private function getFiles(string $pathname, bool $recursive): Finder
    {
        return Finder::create([
            'pathname' => $pathname,
            'mode' => Finder::MODE_FILES_ONLY,
            'recursive' => $recursive,
        ]);
    }
}
