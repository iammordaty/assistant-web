<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierRequestException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\IncomingTrack;
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
     * @var list<array{type: string, pathname: string, message: string}|null>
     */
    private array $recentOutcomes = [];

    public function __construct(
        Logger $logger,
        private MusicClassifierService $musicClassifierService,
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
                'not_readable' => 0,
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
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Calculate metadata and report mismatches without saving anything to database',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force metadata calculation even if metadata is already stored in database',
            )
            ->addOption(
                'clean',
                'c',
                InputOption::VALUE_NONE,
                'Remove metadata of tracks that are no longer present in collection',
            )
            ->addOption(
                'skip-not-calculated',
                null,
                InputOption::VALUE_NONE,
                'Skip tracks that do not have metadata stored in database',
            )
            ->addOption(
                'stat',
                null,
                InputOption::VALUE_NONE,
                'Display statistics: count of MP3 files and database metadata records',
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
            return $this->cleanOrphanedMetadata($output);
        }

        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');
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
                $calculated = $this->processFile($file, $force, $skipNotCalculated, $dryRun);
            } catch (MusicClassifierRequestException $e) {
                $this->stats['error']['classifier']++;

                $error = $this->describeError('classifier', $file, $e);

                $this->logger->error($e->getMessage(), [ 'pathname' => $file->getPathname() ]);
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
    private function describeError(string $type, SplFileInfo $file, \Throwable $e): array
    {
        return [
            'type' => $type,
            'pathname' => $file->getPathname(),
            'message' => $e->getMessage(),
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

    /** @return list<array{type: string, pathname: string, message: string}> */
    private function getErrorsInWindow(): array
    {
        return array_values(
            array_filter($this->recentOutcomes, static fn(?array $outcome): bool => $outcome !== null),
        );
    }

    /**
     * Logs a verbose report explaining why the task stops: the music classifier (essentia) started
     * failing too often, which usually means the external service itself is broken rather than the
     * individual tracks. The report states how many tracks failed, of what kind, with which messages
     * and file paths, so the cause can be diagnosed from the log alone.
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
     * Processes a single track: (re)calculates its metadata when needed and stores it in the
     * database. Returns true when metadata was (re)calculated, so progress reporting can react.
     */
    private function processFile(SplFileInfo $file, bool $force, bool $skipNotCalculated, bool $dryRun): bool
    {
        $track = $this->trackService->createFromFile($file->getPathname());

        if ($track === null) {
            $this->stats['skipped']['not_readable']++;

            $this->logger->warning('Skipping unreadable track', [ 'pathname' => $file->getPathname() ]);

            return false;
        }

        $metadata = $this->musicClassifierMetadataRepository->getByTrackGuid($track->getGuid());
        $action = $this->decideAction($metadata, $force, $skipNotCalculated);

        if ($action === self::ACTION_SKIP_NOT_CALCULATED) {
            $this->stats['skipped']['not_calculated']++;

            $this->logger->debug('Skipping track without stored metadata', [
                'pathname' => $file->getPathname(),
            ]);

            return false;
        }

        if ($action === self::ACTION_SKIP_ALREADY_CALCULATED) {
            $this->stats['skipped']['already_calculated']++;

            return false;
        }

        $result = $this->calculateMetadata($file, $metadata !== null);

        if (!$dryRun) {
            $this->saveMetadata($track, $result);
        }

        $this->countMismatches($track, $result);

        return true;
    }

    private function decideAction(
        ?MusicClassifierMetadataDto $metadata,
        bool $force,
        bool $skipNotCalculated,
    ): string {
        if ($metadata === null) {
            return $skipNotCalculated ? self::ACTION_SKIP_NOT_CALCULATED : self::ACTION_CALCULATE;
        }

        return $force ? self::ACTION_CALCULATE : self::ACTION_SKIP_ALREADY_CALCULATED;
    }

    private function calculateMetadata(SplFileInfo $file, bool $hadStoredMetadata): MusicClassifierResult
    {
        $result = $this->musicClassifierService->analyze($file);

        $this->stats['calculated']++;

        $this->logger->info(
            sprintf('%s metadata for track', $hadStoredMetadata ? 'Updated' : 'Calculated'),
            [ 'pathname' => $file->getPathname() ],
        );

        return $result;
    }

    private function saveMetadata(IncomingTrack|Track $track, MusicClassifierResult $result): void
    {
        $this->musicClassifierMetadataRepository->save(
            MusicClassifierMetadataDto::fromResult($track->getGuid(), $result),
        );

        $this->stats['saved']++;
    }

    private function showStats(InputInterface $input, OutputInterface $output): int
    {
        $pathname = $input->getArgument('pathname');
        $files = $this->getFiles($pathname);
        $mp3Count = count($files);

        $dbCount = $this->musicClassifierMetadataRepository->count();

        $output->writeln(sprintf('MP3 files in collection: %d', $mp3Count));
        $output->writeln(sprintf('Metadata records in database: %d', $dbCount));

        $this->logger->info('Statistics', [
            'mp3_files' => $mp3Count,
            'db_records' => $dbCount,
        ]);

        return self::SUCCESS;
    }

    /**
     * Usuwa metadane utworów, których nie ma już w kolekcji. Utwór uznaje się za nieistniejący,
     * gdy jego guid nie występuje w indeksie kolekcji.
     */
    private function cleanOrphanedMetadata(OutputInterface $output): int
    {
        $orphanedTrackGuids = [];

        foreach ($this->musicClassifierMetadataRepository->getTrackGuids() as $trackGuid) {
            if ($this->trackService->getByGuid($trackGuid) !== null) {
                continue;
            }

            $orphanedTrackGuids[] = $trackGuid;

            $this->logger->info('Removing orphaned metadata', [ 'trackGuid' => $trackGuid ]);
        }

        $cleanedCount = $this->musicClassifierMetadataRepository->removeByTrackGuids($orphanedTrackGuids);

        $this->stats['cleaned'] = $cleanedCount;

        $output->writeln(sprintf('Cleaned %d orphaned metadata record(s).', $cleanedCount));
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
    private function countMismatches(IncomingTrack|Track $track, MusicClassifierResult $result): void
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
