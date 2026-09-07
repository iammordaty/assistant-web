<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierProcessException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Search\Extension\Criteria\Regex;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Track\Model\MusicClassifierMetadataDto;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\MusicClassifierMetadataRepository;
use Assistant\Module\Track\Repository\TrackRepository;
use KeyTools\KeyTools;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task zapisujący w bazie danych metadane z klasyfikatora audio dla utworów z kolekcji.
 *
 * Dla utworów, które nie mają jeszcze wyniku klasyfikacji, uruchamiany jest ekstraktor, a powstały
 * plik trafia do lokalizacji odpowiadającej ścieżce utworu. Utwory z zapisanymi metadanymi są
 * pomijane, dzięki czemu przebieg można przerwać i wznowić bez utraty postępu.
 */
final class MusicClassifierMetadataCalculatorTask extends AbstractTask
{
    protected static $defaultName = 'track:calculate-music-classifier-metadata';

    private array $stats;

    public function __construct(
        Logger $logger,
        private MusicClassifierService $musicClassifierService,
        private MusicClassifierMetadataRepository $musicClassifierMetadataRepository,
        private TrackRepository $trackRepository,
        private KeyTools $keyTools,
    ) {
        parent::__construct($logger);

        $this->stats = [
            'processed' => 0,
            'calculated' => 0,
            'saved' => 0,
            'skipped' => [ 'already_stored' => 0 ],
            'mismatch' => [ 'bpm' => 0, 'initial_key' => 0 ],
            'error' => [ 'classifier' => 0, 'result' => 0, 'other' => 0 ],
        ];
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(MusicClassifierService::class),
            $container->get(MusicClassifierMetadataRepository::class),
            $container->get(TrackRepository::class),
            KeyTools::fromNotation(KeyTools::NOTATION_MUSICAL_ESSENTIA),
        );
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Stores music classifier metadata for collection tracks in database')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Process tracks with already stored metadata',
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of tracks to process',
            )
            ->addOption(
                'pathname',
                'p',
                InputOption::VALUE_REQUIRED,
                'Process only tracks with pathname starting with given value',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('Task executed', self::getInputParams($input));

        $force = $input->getOption('force');
        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;

        $storedTrackGuids = $force === true
            ? []
            : array_flip($this->musicClassifierMetadataRepository->getTrackGuids());

        $criteria = $this->getSearchCriteria($input->getOption('pathname'));

        // repozytorium zwraca leniwy generator, więc kolekcja nie jest ładowana do pamięci
        foreach ($this->trackRepository->findBy($criteria) as $track) {
            if ($limit !== null && $this->stats['processed'] >= $limit) {
                break;
            }

            if (isset($storedTrackGuids[$track->getGuid()])) {
                $this->stats['skipped']['already_stored']++;

                unset($track);

                continue;
            }

            $this->stats['processed']++;

            $this->logger->debug('Processing track', [ 'pathname' => $track->getPathname() ]);

            try {
                $result = $this->getClassifierResult($track);

                $this->musicClassifierMetadataRepository->save(
                    MusicClassifierMetadataDto::fromResult($track->getGuid(), $result),
                );

                $this->stats['saved']++;

                $this->countMismatches($track, $result);
            } catch (MusicClassifierProcessException $e) {
                $this->stats['error']['classifier']++;

                $this->logger->error($e->getMessage(), [
                    'pathname' => $track->getPathname(),
                    'commandLine' => $e->getProcessCommandLine(),
                ]);
            } catch (MusicClassifierException $e) {
                $this->stats['error']['result']++;

                $this->logger->error($e->getMessage(), [ 'pathname' => $track->getPathname() ]);
            } catch (\Throwable $e) {
                $this->stats['error']['other']++;

                $this->logger->critical($e->getMessage(), [ 'pathname' => $track->getPathname() ]);
            } finally {
                unset($track, $result);
            }
        }

        $this->logger->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /** Ogranicza przebieg do utworów z podanej gałęzi kolekcji, gdy podano ścieżkę */
    private function getSearchCriteria(?string $pathname): SearchCriteria
    {
        if ($pathname === null) {
            return new SearchCriteria();
        }

        // wzorzec jest cytowany, ponieważ Regex wstawia go bez zmian, a ścieżki w kolekcji zawierają
        // znaki o znaczeniu specjalnym w wyrażeniu regularnym
        return new SearchCriteria(pathname: [ Regex::startsWith(preg_quote($pathname)) ]);
    }

    /**
     * Zwraca wynik klasyfikacji utworu. Gotowy wynik jest odczytywany z dysku, a jego brak oznacza
     * uruchomienie ekstraktora — najdroższą operację w całym przebiegu.
     */
    private function getClassifierResult(Track $track): MusicClassifierResult
    {
        $result = $this->musicClassifierService->getResult($track->getFile());

        if ($result) {
            return $result;
        }

        $result = $this->musicClassifierService->analyze($track->getFile());

        $this->musicClassifierService->moveResultToIndexedLocation($track->getFile(), $result);

        $this->stats['calculated']++;

        return $result;
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
