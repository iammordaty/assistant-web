<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierAudioMd5Calculator;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierProcessException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\MusicClassifierMetadataDto;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\MusicClassifierMetadataRepository;
use KeyTools\KeyTools;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder as SymfonyFinder;

final class MusicClassifierMetadataCalculatorTask extends AbstractTask
{
    protected static $defaultName = 'track:calculate-music-classifier-metadata';

    private const string ACTION_CALCULATE = 'calculate';
    private const string ACTION_SKIP_NOT_CALCULATED = 'skip_not_calculated';
    private const string ACTION_SKIP_ALREADY_CALCULATED = 'skip_already_calculated';

    /** Number of most recently processed tracks the error-rate circuit breaker looks at. */
    private const int ERROR_RATE_WINDOW_SIZE = 20;

    /** Task aborts once this many of the last ERROR_RATE_WINDOW_SIZE tracks failed. */
    private const int ERROR_RATE_MAX_ERRORS = 10;

    private array $stats;

    /**
     * Sliding window of the most recently processed tracks. Each entry is either null (success) or
     * an error detail array; it powers the circuit breaker and the verbose abort report.
     *
     * @var list<array{type: string, pathname: string, message: string, commandLine: string|null}|null>
     */
    private array $recentOutcomes = [];

    public function __construct(
        Logger $logger,
        private MusicClassifierService $musicClassifierService,
        private MusicClassifierAudioMd5Calculator $audioMd5Calculator,
        private MusicClassifierMetadataRepository $musicClassifierMetadataRepository,
        private TrackService $trackService,
        private KeyTools $keyTools,
        private Config $config,
    ) {
        parent::__construct($logger);

        $this->stats = [
            'processed' => 0,
            'calculated' => 0,
            'saved' => 0,
            'cleaned' => 0,
            'skipped' => [
                'already_calculated' => 0,
                'not_calculated' => 0,
            ],
            'mismatch' => [
                'bpm' => 0,
                'initial_key' => 0,
            ],
            'error' => [
                'classifier' => 0,
                'result' => 0,
                'other' => 0,
            ],
        ];
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(MusicClassifierService::class),
            $container->get(MusicClassifierAudioMd5Calculator::class),
            $container->get(MusicClassifierMetadataRepository::class),
            $container->get(TrackService::class),
            KeyTools::fromNotation(KeyTools::NOTATION_MUSICAL_ESSENTIA),
            $container->get(Config::class),
        );
    }

    protected function configure(): void
    {
        $collectionRootDir = $this->config->get('collection.root_dir');

        $this
            ->setDescription('Calculates and stores music classifier metadata for tracks in collection')
            ->addArgument(
                'pathname',
                InputArgument::OPTIONAL,
                'Pathname to search tracks to process',
                $collectionRootDir,
            )
            ->addOption(
                'save-to-db',
                's',
                InputOption::VALUE_NONE,
                'Save metadata to database',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force metadata calculation even if metadata JSON file already exists',
            )
            ->addOption(
                'update-if-chromaprint-differs',
                'u',
                InputOption::VALUE_NONE,
                'Calculate metadata if audio chromaprint/MD5 differs from stored metadata',
            )
            ->addOption(
                'clean',
                'c',
                InputOption::VALUE_NONE,
                'Remove metadata JSON files for non-existent MP3 files',
            )
            ->addOption(
                'skip-not-calculated',
                null,
                InputOption::VALUE_NONE,
                'Skip tracks that do not have metadata in JSON files',
            )
            ->addOption(
                'stat',
                null,
                InputOption::VALUE_NONE,
                'Display statistics: count of MP3 files, JSON files, and database metadata records',
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of tracks to process',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('Task executed', self::getInputParams($input));

        if ($input->getOption('stat')) {
            return $this->showStats($input, $output);
        }

        if ($input->getOption('clean')) {
            return $this->cleanOrphanedMetadataFiles($output);
        }

        $force = (bool) $input->getOption('force');
        $saveToDb = (bool) $input->getOption('save-to-db');
        $updateIfChromaprintDiffers = (bool) $input->getOption('update-if-chromaprint-differs');
        $skipNotCalculated = (bool) $input->getOption('skip-not-calculated');
        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;

        $files = $this->getFiles($input->getArgument('pathname'));

        $totalFiles = $limit !== null ? min($limit, count($files)) : count($files);

        $startTime = microtime(true);
        $lastReportTime = $startTime;

        foreach ($files as $file) {
            if ($limit !== null && $this->stats['processed'] >= $limit) {
                break;
            }

            $this->stats['processed']++;

            $this->logger->debug('Processing track', [ 'pathname' => $file->getPathname() ]);

            $calculated = false;
            $error = null;

            try {
                $calculated = $this->processFile(
                    $file,
                    $force,
                    $updateIfChromaprintDiffers,
                    $skipNotCalculated,
                    $saveToDb,
                );
            } catch (MusicClassifierProcessException $e) {
                $this->stats['error']['classifier']++;

                $error = $this->describeError('classifier', $file, $e, $e->getProcessCommandLine());

                $this->logger->error($e->getMessage(), [
                    'pathname' => $file->getPathname(),
                    'commandLine' => $e->getProcessCommandLine(),
                ]);
            } catch (MusicClassifierException $e) {
                $this->stats['error']['result']++;

                $error = $this->describeError('result', $file, $e);

                $this->logger->error($e->getMessage(), [ 'pathname' => $file->getPathname() ]);
            } catch (\Throwable $e) {
                $this->stats['error']['other']++;

                $error = $this->describeError('other', $file, $e);

                $this->logger->critical($e->getMessage(), [ 'pathname' => $file->getPathname() ]);
            }

            $this->registerOutcome($error);
            $this->reportProgressIfNeeded($totalFiles, $startTime, $lastReportTime, $calculated);

            if ($this->errorsAreTooFrequent()) {
                $this->reportErrorRateExceeded();

                return self::FAILURE;
            }
        }

        $this->logger->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /** Builds an error detail entry describing a single failed track for the circuit breaker report. */
    private function describeError(
        string $type,
        SplFileInfo $file,
        \Throwable $e,
        ?string $commandLine = null,
    ): array {
        return [
            'type' => $type,
            'pathname' => $file->getPathname(),
            'message' => $e->getMessage(),
            'commandLine' => $commandLine,
        ];
    }

    /** Records the outcome of a processed track (null = success) into the sliding window. */
    private function registerOutcome(?array $error): void
    {
        $this->recentOutcomes[] = $error;

        if (count($this->recentOutcomes) > self::ERROR_RATE_WINDOW_SIZE) {
            array_shift($this->recentOutcomes);
        }
    }

    /** Tells whether the recent tracks failed often enough to consider the classifier broken. */
    private function errorsAreTooFrequent(): bool
    {
        return count($this->getErrorsInWindow()) >= self::ERROR_RATE_MAX_ERRORS;
    }

    /** @return list<array{type: string, pathname: string, message: string, commandLine: string|null}> */
    private function getErrorsInWindow(): array
    {
        return array_values(
            array_filter($this->recentOutcomes, static fn(?array $outcome): bool => $outcome !== null),
        );
    }

    /**
     * Logs a verbose report explaining why the task stops: the music classifier (essentia) started
     * failing too often, which usually means the external service itself is broken rather than the
     * individual tracks. The report states how many tracks failed, of what kind, with which messages,
     * command lines, and file paths, so the cause can be diagnosed from the log alone.
     */
    private function reportErrorRateExceeded(): void
    {
        $errors = $this->getErrorsInWindow();

        $errorsByType = [];

        foreach ($errors as $error) {
            $errorsByType[$error['type']] = ($errorsByType[$error['type']] ?? 0) + 1;
        }

        $this->logger->critical('Aborting task: music classifier (essentia) is failing too often', [
            'reason' => sprintf(
                'At least %d of the last %d processed tracks failed (allowed: %d)',
                count($errors),
                count($this->recentOutcomes),
                self::ERROR_RATE_MAX_ERRORS,
            ),
            'errorsInWindow' => count($errors),
            'windowSize' => count($this->recentOutcomes),
            'threshold' => self::ERROR_RATE_MAX_ERRORS,
            'errorsByType' => $errorsByType,
            'errors' => $errors,
            'stats' => $this->stats,
        ]);
    }

    /**
     * Processes a single track: (re)calculates its metadata when needed and optionally saves it to
     * the database. Returns true when metadata was (re)calculated, so progress reporting can react.
     */
    private function processFile(
        SplFileInfo $file,
        bool $force,
        bool $updateIfChromaprintDiffers,
        bool $skipNotCalculated,
        bool $saveToDb,
    ): bool {
        $result = $this->musicClassifierService->getResult($file);
        $action = $this->decideAction($file, $result, $force, $updateIfChromaprintDiffers, $skipNotCalculated);

        if ($action === self::ACTION_SKIP_NOT_CALCULATED) {
            $this->stats['skipped']['not_calculated']++;

            $this->logger->debug('Skipping track without JSON metadata', [ 'pathname' => $file->getPathname() ]);

            return false;
        }

        $calculated = $action === self::ACTION_CALCULATE;

        if ($calculated) {
            $result = $this->calculateMetadata($file, $result !== null);
        } else {
            $this->stats['skipped']['already_calculated']++;
        }

        if ($saveToDb && $result !== null) {
            $this->saveMetadata($file, $result);
        }

        return $calculated;
    }

    private function decideAction(
        SplFileInfo $file,
        ?MusicClassifierResult $result,
        bool $force,
        bool $updateIfChromaprintDiffers,
        bool $skipNotCalculated,
    ): string {
        if ($result === null) {
            return $skipNotCalculated ? self::ACTION_SKIP_NOT_CALCULATED : self::ACTION_CALCULATE;
        }

        if ($force) {
            return self::ACTION_CALCULATE;
        }

        if ($updateIfChromaprintDiffers && $this->audioMd5Calculator->calculate($file) !== $result->getMd5()) {
            return self::ACTION_CALCULATE;
        }

        return self::ACTION_SKIP_ALREADY_CALCULATED;
    }

    private function calculateMetadata(SplFileInfo $file, bool $hadExistingResult): MusicClassifierResult
    {
        $result = $this->musicClassifierService->analyze($file);

        $this->musicClassifierService->moveResultToIndexedLocation($file, $result);

        $this->stats['calculated']++;

        $this->logger->info(
            sprintf('%s metadata for track', $hadExistingResult ? 'Updated' : 'Calculated'),
            [ 'pathname' => $file->getPathname() ],
        );

        return $result;
    }

    private function saveMetadata(SplFileInfo $file, MusicClassifierResult $result): void
    {
        $track = $this->trackService->createFromFile($file->getPathname());

        if ($track === null) {
            return;
        }

        $this->musicClassifierMetadataRepository->save(
            MusicClassifierMetadataDto::fromResult($track->getGuid(), $result),
        );

        $this->stats['saved']++;

        $this->countMismatches($track, $result);
    }

    private function showStats(InputInterface $input, OutputInterface $output): int
    {
        $pathname = $input->getArgument('pathname');
        $files = $this->getFiles($pathname);
        $mp3Count = count($files);

        $metadataRootDir = $this->config->get('collection.metadata_dirs.music_classifier');
        $jsonCount = 0;

        if (is_dir($metadataRootDir)) {
            $jsonFinder = (new SymfonyFinder())
                ->files()
                ->in($metadataRootDir)
                ->name('*.json');

            $jsonCount = $jsonFinder->count();
        }

        $dbCount = $this->musicClassifierMetadataRepository->count();

        $output->writeln(sprintf('MP3 files in collection: %d', $mp3Count));
        $output->writeln(sprintf('JSON metadata files: %d', $jsonCount));
        $output->writeln(sprintf('Metadata records in database: %d', $dbCount));

        $this->logger->info('Statistics', [
            'mp3_files' => $mp3Count,
            'json_files' => $jsonCount,
            'db_records' => $dbCount,
        ]);

        return self::SUCCESS;
    }

    private function cleanOrphanedMetadataFiles(OutputInterface $output): int
    {
        $metadataRootDir = $this->config->get('collection.metadata_dirs.music_classifier');
        $collectionRootDir = $this->config->get('collection.root_dir');

        if (!is_dir($metadataRootDir)) {
            $this->logger->info('Metadata directory does not exist', [ 'dir' => $metadataRootDir ]);

            return self::SUCCESS;
        }

        $finder = (new SymfonyFinder())
            ->files()
            ->in($metadataRootDir)
            ->name('*.json');

        $cleanedCount = 0;

        foreach ($finder as $jsonFile) {
            $jsonPathname = $jsonFile->getPathname();

            $relativePath = substr($jsonPathname, strlen($metadataRootDir));
            $mp3RelativePath = preg_replace('/\.json$/', '.mp3', $relativePath);
            $expectedMp3Pathname = $collectionRootDir . $mp3RelativePath;

            if (!file_exists($expectedMp3Pathname)) {
                unlink($jsonPathname);

                $cleanedCount++;
                $this->stats['cleaned']++;

                $this->logger->info('Removed orphaned metadata file', [
                    'pathname' => $jsonPathname,
                    'expectedMp3' => $expectedMp3Pathname,
                ]);
            }
        }

        $output->writeln(sprintf('Cleaned %d orphaned metadata JSON file(s).', $cleanedCount));
        $this->logger->info('Cleanup finished', [ 'cleaned' => $cleanedCount ]);

        return self::SUCCESS;
    }

    /** @return SplFileInfo[]|Finder */
    private function getFiles(string $pathname): array|Finder
    {
        $collectionRootDir = $this->config->get('collection.root_dir');
        $isRoot = $pathname === $collectionRootDir;

        $params = [
            'pathname' => $pathname,
            'mode' => Finder::MODE_FILES_ONLY,
            'recursive' => is_dir($pathname),
            'skip_self' => false,
        ];

        if ($isRoot) {
            $params['restrict'] = $this->config->get('collection.indexed_dirs');
        }

        return Finder::create($params);
    }

    private function reportProgressIfNeeded(
        int $totalFiles,
        float $startTime,
        float &$lastReportTime,
        bool $calculated,
    ): void {
        $processed = $this->stats['processed'];
        $now = microtime(true);

        $shouldReport = $calculated
            || ($processed % 50 === 0)
            || ($now - $lastReportTime >= 10.0)
            || ($processed >= $totalFiles);

        if (!$shouldReport || $processed === 0) {
            return;
        }

        $lastReportTime = $now;
        $remaining = max(0, $totalFiles - $processed);
        $elapsed = $now - $startTime;
        $avgTimePerFile = $elapsed / $processed;
        $estimatedRemainingSeconds = (int) round($remaining * $avgTimePerFile);
        $estimatedTime = $this->formatDuration($estimatedRemainingSeconds);
        $percent = $totalFiles > 0 ? ($processed / $totalFiles) * 100 : 100;

        $message = sprintf(
            'Progress: %d/%d (%.1f%%) tracks processed, %d remaining, estimated time remaining: ~%s',
            $processed,
            $totalFiles,
            $percent,
            $remaining,
            $estimatedTime,
        );

        $this->logger->info($message);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%ds', $seconds);
        }

        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $secs);
        }

        return sprintf('%dm %02ds', $minutes, $secs);
    }

    /** Zlicza rozbieżności między metadanymi utworu a wartościami wyliczonymi przez klasyfikator */
    private function countMismatches(Track $track, MusicClassifierResult $result): void
    {
        $bpm = $track->getBpm();
        $initialKey = $track->getInitialKey();

        // bpm z wyniku jest zaokrąglone do jednego miejsca po przecinku, stąd porównanie z tolerancją
        if ($bpm !== null && abs($bpm - $result->getBpm()) >= 0.1) {
            $this->stats['mismatch']['bpm']++;

            $this->logger->info('Track bpm differs from calculated value', [
                'pathname' => $track->getPathname(),
                'bpm' => $bpm,
                'calculatedBpm' => $result->getBpm(),
            ]);
        }

        if ($initialKey === null) {
            return;
        }

        $calculatedKey = $this->keyTools->convertKeyToNotation(
            $result->getMusicalKey(),
            KeyTools::NOTATION_CAMELOT_KEY,
        );

        if ($initialKey !== $calculatedKey) {
            $this->stats['mismatch']['initial_key']++;

            $this->logger->info('Track initial key differs from calculated value', [
                'pathname' => $track->getPathname(),
                'initialKey' => $initialKey,
                'calculatedInitialKey' => $calculatedKey,
            ]);
        }
    }
}
