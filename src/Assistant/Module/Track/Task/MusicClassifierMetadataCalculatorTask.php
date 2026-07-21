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
use Throwable;

/** Task wyliczający metadane utworów przy pomocy klasyfikatora audio */
final class MusicClassifierMetadataCalculatorTask extends AbstractTask
{
    protected static $defaultName = 'track:calculate-music-classifier-metadata';

    private const string ACTION_CALCULATE = 'calculate';
    private const string ACTION_SKIP_NOT_CALCULATED = 'skip_not_calculated';
    private const string ACTION_SKIP_ALREADY_CALCULATED = 'skip_already_calculated';

    /** Liczba ostatnio przetworzonych utworów branych pod uwagę przez bezpiecznik */
    private const int ERROR_RATE_WINDOW_SIZE = 20;

    /** Liczba błędów w oknie, po której task zostaje przerwany */
    private const int ERROR_RATE_MAX_ERRORS = 10;

    private array $stats;

    /**
     * Okno ostatnio przetworzonych utworów. Pojedynczy wpis to null (sukces) albo opis błędu;
     * na tej podstawie działa bezpiecznik oraz raport kończący task.
     *
     * @var list<array{ type: string, pathname: string, message: string }|null>
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
            } catch (Throwable $e) {
                $this->stats['error']['other']++;

                $error = $this->describeError('other', $file, $e);

                $this->logger->critical($e->getMessage(), [ 'pathname' => $file->getPathname() ]);
            }

            $this->registerOutcome($error);
            $this->reportProgress($totalFiles, $startTime, $lastReportTime, $calculated);

            if ($this->hasTooFrequentErrors()) {
                $this->reportErrorRateExceeded();

                return self::FAILURE;
            }
        }

        $this->logger->debug('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /**
     * Przetwarza pojedynczy utwór: wylicza metadane, jeśli zachodzi taka potrzeba, i zapisuje je
     * w bazie danych. Zwraca informację o tym, czy metadane zostały wyliczone.
     */
    private function processFile(SplFileInfo $file, bool $force, bool $skipNotCalculated, bool $dryRun): bool
    {
        $track = $this->trackService->createFromFile($file->getPathname());

        if (!$track) {
            $this->stats['skipped']['not_readable']++;

            $this->logger->warning('Track is not readable, skipping', [ 'pathname' => $file->getPathname() ]);

            return false;
        }

        $musicClassifierMetadata = $this->musicClassifierMetadataRepository->getByTrackGuid($track->getGuid());
        $action = $this->decideAction($musicClassifierMetadata, $force, $skipNotCalculated);

        if ($action === self::ACTION_SKIP_NOT_CALCULATED) {
            $this->stats['skipped']['not_calculated']++;

            $this->logger->debug('Track does not have stored metadata, skipping', [
                'pathname' => $file->getPathname(),
            ]);

            return false;
        }

        if ($action === self::ACTION_SKIP_ALREADY_CALCULATED) {
            $this->stats['skipped']['already_calculated']++;

            $this->logger->debug('Track is already calculated, skipping', [
                'pathname' => $file->getPathname(),
            ]);

            return false;
        }

        $result = $this->calculateMetadata($file, $musicClassifierMetadata !== null);

        if (!$dryRun) {
            $this->saveMetadata($track, $result);
        }

        $this->countMismatches($track, $result);

        return true;
    }

    /** Rozstrzyga, co należy zrobić z utworem o podanych metadanych */
    private function decideAction(
        ?MusicClassifierMetadataDto $musicClassifierMetadata,
        bool $force,
        bool $skipNotCalculated,
    ): string {
        if (!$musicClassifierMetadata) {
            return $skipNotCalculated ? self::ACTION_SKIP_NOT_CALCULATED : self::ACTION_CALCULATE;
        }

        return $force ? self::ACTION_CALCULATE : self::ACTION_SKIP_ALREADY_CALCULATED;
    }

    /** Wylicza metadane utworu przy pomocy klasyfikatora audio */
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

    /** Zapisuje metadane utworu w bazie danych */
    private function saveMetadata(IncomingTrack|Track $track, MusicClassifierResult $result): void
    {
        $musicClassifierMetadata = MusicClassifierMetadataDto::fromResult($track->getGuid(), $result);

        $this->musicClassifierMetadataRepository->save($musicClassifierMetadata);

        $this->stats['saved']++;
    }

    /** Wyświetla liczbę utworów w kolekcji oraz liczbę zapisanych metadanych */
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
     * jeżeli jego guid nie występuje w indeksie kolekcji.
     */
    private function cleanOrphanedMetadata(OutputInterface $output): int
    {
        $orphanedTrackGuids = [];

        foreach ($this->musicClassifierMetadataRepository->getTrackGuids() as $trackGuid) {
            if ($this->trackService->getByGuid($trackGuid)) {
                continue;
            }

            $orphanedTrackGuids[] = $trackGuid;

            $this->logger->info('Removing orphaned metadata', [ 'trackGuid' => $trackGuid ]);
        }

        $cleaned = $this->musicClassifierMetadataRepository->removeByTrackGuids($orphanedTrackGuids);

        $this->stats['cleaned'] = $cleaned;

        $output->writeln(sprintf('Cleaned %d orphaned metadata record(s).', $cleaned));

        $this->logger->info('Cleanup finished', [ 'cleaned' => $cleaned ]);

        return self::SUCCESS;
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

    /** Zwraca opis błędu pojedynczego utworu, używany przez bezpiecznik */
    private function describeError(string $type, SplFileInfo $file, Throwable $e): array
    {
        return [
            'type' => $type,
            'pathname' => $file->getPathname(),
            'message' => $e->getMessage(),
        ];
    }

    /** Dopisuje wynik przetworzenia utworu (null oznacza sukces) do okna ostatnich wyników */
    private function registerOutcome(?array $error): void
    {
        $this->recentOutcomes[] = $error;

        if (count($this->recentOutcomes) > self::ERROR_RATE_WINDOW_SIZE) {
            array_shift($this->recentOutcomes);
        }
    }

    /** Informuje, czy ostatnie utwory kończyły się błędem na tyle często, żeby przerwać task */
    private function hasTooFrequentErrors(): bool
    {
        return count($this->getErrorsInWindow()) >= self::ERROR_RATE_MAX_ERRORS;
    }

    /** @return list<array{ type: string, pathname: string, message: string }> */
    private function getErrorsInWindow(): array
    {
        $errors = array_filter(
            $this->recentOutcomes,
            static fn (?array $outcome): bool => $outcome !== null,
        );

        return array_values($errors);
    }

    /**
     * Zapisuje w logu powód przerwania tasku: klasyfikator zaczął zawodzić na tyle często, że
     * najprawdopodobniej niedostępny jest sam serwis, a nie poszczególne utwory. Raport podaje
     * liczbę i rodzaj błędów, ich komunikaty oraz ścieżki utworów, żeby przyczynę dało się ustalić
     * na podstawie samego logu.
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

    /** Zapisuje w logu postęp przetwarzania wraz z szacowanym czasem pozostałym do końca */
    private function reportProgress(
        int $totalFiles,
        float $startTime,
        float &$lastReportTime,
        bool $calculated,
    ): void {
        $now = microtime(true);

        if (!$this->isProgressReportNeeded($totalFiles, $lastReportTime, $now, $calculated)) {
            return;
        }

        $lastReportTime = $now;

        $processed = $this->stats['processed'];
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

    /** Informuje, czy postęp przetwarzania powinien zostać zapisany w logu */
    private function isProgressReportNeeded(
        int $totalFiles,
        float $lastReportTime,
        float $now,
        bool $calculated,
    ): bool {
        $processed = $this->stats['processed'];

        if ($processed === 0) {
            return false;
        }

        return $calculated
            || ($processed % 50 === 0)
            || ($now - $lastReportTime >= 10.0)
            || ($processed >= $totalFiles);
    }

    /** Zwraca czas trwania w postaci czytelnej dla człowieka */
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
}
