<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Search\Extension\Criteria\Not;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\CandidateProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Extension\Similarity\SimilarityBuilder;
use Assistant\Module\Track\Extension\Similarity\SimilarTracks;
use Assistant\Module\Track\Model\Track;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Mierzy rozkład wyników modułu podobieństwa na próbce kolekcji.
 *
 * Raport odpowiada na pytania, których nie widać w interfejsie: jak rozłożone są wyniki końcowe, ilu
 * kandydatów odrzuca próg, jaki rozkład wartości ma każdy dostawca i jak często nie ma on danych oraz
 * ilu sąsiadów wskazanych przez Musly nie przechodzi progu. Bez tych liczb strojenie wag i progu jest
 * zgadywaniem.
 *
 * Próbka pochodzi z cache'owanej listy losowych utworów, więc dwa przebiegi obejmują te same utwory
 * bazowe i dają się porównać.
 *
 * @fixme Wybór kandydatów (getCandidates, getCandidateCriteria, getSimilarityCriteria) powtarza logikę
 *        prywatnych metod klasy Similarity, ponieważ raport potrzebuje zbioru kandydatów przed progiem,
 *        a modułu podobieństwa nie otwieramy metodą publiczną tylko na potrzeby diagnostyki. Docelowo
 *        wybór kandydatów należy wydzielić do osobnej klasy, używanej zarówno przez moduł, jak i przez
 *        ten raport - wtedy te trzy metody znikają. Dopóki duplikacja istnieje, zmiana kryteriów
 *        w Similarity wymaga tej samej zmiany tutaj, inaczej raport przestanie opisywać rzeczywistość.
 */
final class SimilarityReportTask extends AbstractTask
{
    private const int DEFAULT_SAMPLE_SIZE = 25;

    protected static $defaultName = 'track:similarity-report';

    public function __construct(
        Logger $logger,
        private SimilarityBuilder $similarityBuilder,
        private TrackSearchService $searchService,
    ) {
        parent::__construct($logger);
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(SimilarityBuilder::class),
            $container->get(TrackSearchService::class),
        );
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Reports similarity score distribution and per-provider values')
            ->addOption(
                'sample',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of base tracks to analyze',
                self::DEFAULT_SAMPLE_SIZE,
            )
            ->addOption(
                'report-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Path of the CSV file with per-pair values',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('Task executed', self::getInputParams($input));

        $sampleSize = (int) $input->getOption('sample');
        $baseTracks = $this->searchService->getRandom($sampleSize);

        if (!$baseTracks) {
            $output->writeln('<error>Collection is empty, nothing to report</error>');

            return self::FAILURE;
        }

        $similarityService = $this->similarityBuilder->getSimilarityService();

        $candidateCounts = [];
        $resultCounts = [];
        $candidateSimilarityValues = [];
        $providerValues = [];
        $neighbourCounts = [];
        $rejectedNeighbourCounts = [];
        $rows = [];

        foreach ($baseTracks as $baseTrack) {
            $candidates = $this->getCandidates($baseTrack);
            $similarTracks = $similarityService->getSimilarTracks($baseTrack);

            $candidateCounts[] = count($candidates);
            $resultCounts[] = count($similarTracks);

            foreach ($candidates as $candidate) {
                $candidateSimilarityValues[] = $similarityService->getSimilarityValue($baseTrack, $candidate);
            }

            // wartości dostawców liczone są dla par, które przeszły próg, żeby raport nie mnożył
            // porównań przez cały zbiór kandydatów
            foreach ($similarTracks as $similarTrack) {
                $comparedTrack = $similarTrack->getSecondTrack();
                $values = $this->getProviderSimilarityValues($baseTrack, $comparedTrack);

                foreach ($values as $providerName => $value) {
                    $providerValues[$providerName][] = $value;
                }

                $rows[] = array_merge(
                    [
                        $baseTrack->getGuid(),
                        $comparedTrack->getGuid(),
                        (int) round($similarTrack->getSimilarityValue()),
                    ],
                    array_map(static fn (?int $value) => $value ?? '', array_values($values)),
                );
            }

            [ $neighbourCounts[], $rejectedNeighbourCounts[] ] = $this->countRejectedNeighbours(
                $baseTrack,
                $candidates,
                $similarTracks,
            );
        }

        $this->writeSummary($output, $baseTracks, $candidateCounts, $resultCounts, $candidateSimilarityValues);
        $this->writeProviderDistribution($output, $providerValues);
        $this->writeNeighbourLoss($output, $neighbourCounts, $rejectedNeighbourCounts);

        $reportFile = $input->getOption('report-file');

        if ($reportFile) {
            $this->writeReportFile($reportFile, $rows, array_keys($providerValues));

            $output->writeln(sprintf('Per-pair values written to <info>%s</info>', $reportFile));
        }

        $this->logger->debug('Task finished');

        return self::SUCCESS;
    }

    /**
     * Wartości poszczególnych dostawców dla pary utworów, bez uwzględnienia wag.
     * `null` oznacza, że dostawca nie miał danych.
     *
     * @return array<string, int|null>
     */
    private function getProviderSimilarityValues(Track $baseTrack, Track $comparedTrack): array
    {
        $similarityValues = [];

        foreach ($this->similarityBuilder->getProviders() as $provider) {
            $similarityValues[$provider->getName()] = $provider->getSimilarityValue($baseTrack, $comparedTrack);
        }

        return $similarityValues;
    }

    /**
     * Zbiór kandydatów przed progiem: suma dopasowania metadanych i utworów wskazanych przez dostawców.
     *
     * @fixme patrz opis klasy - duplikat Similarity::getCandidates()
     *
     * @return Track[]
     */
    private function getCandidates(Track $baseTrack): array
    {
        $candidates = [];

        foreach ($this->getCandidateCriteria($baseTrack) as $criteria) {
            $result = $this->searchService->search($criteria);

            foreach ($result->tracks as $candidate) {
                $candidates[$candidate->getGuid()] = $candidate;
            }
        }

        unset($candidates[$baseTrack->getGuid()]);

        return array_values($candidates);
    }

    /**
     * @fixme patrz opis klasy - duplikat Similarity::getCandidateCriteria()
     *
     * @return SearchCriteria[]
     */
    private function getCandidateCriteria(Track $baseTrack): array
    {
        $criteria = [ $this->getSimilarityCriteria($baseTrack) ];

        foreach ($this->getCandidatePathnames($baseTrack) as $pathnames) {
            $criteria[] = new SearchCriteria(
                guid: Not::equal($baseTrack->getGuid()),
                pathname: $pathnames,
            );
        }

        return $criteria;
    }

    /**
     * @fixme patrz opis klasy - duplikat Similarity::getSimilarityCriteria()
     */
    private function getSimilarityCriteria(Track $baseTrack): SearchCriteria
    {
        $providerCriteria = [];

        foreach ($this->similarityBuilder->getProviders() as $provider) {
            $providerCriteria[$provider->getName()] = $provider->getCriteria($baseTrack);
        }

        return new SearchCriteria(
            guid: Not::equal($baseTrack->getGuid()),
            bpm: $providerCriteria[Bpm::NAME] ?? null,
            genres: $providerCriteria[Genre::NAME] ?? null,
            initialKeys: $providerCriteria[MusicalKey::NAME] ?? null,
            years: $providerCriteria[Year::NAME] ?? null,
        );
    }

    /**
     * Ścieżki zgłoszone przez dostawców potrafiących wskazać własnych kandydatów, zgrupowane po
     * dostawcy. Wartości pochodzą z pamięci dostawcy, więc raport nie wywołuje procesu Musly
     * powtórnie dla tego samego utworu bazowego.
     *
     * @return array<string, string[]>
     */
    private function getCandidatePathnames(Track $baseTrack): array
    {
        $pathnames = [];

        foreach ($this->similarityBuilder->getProviders() as $provider) {
            if (!$provider instanceof CandidateProviderInterface) {
                continue;
            }

            $providerPathnames = $provider->getCandidatePathnames($baseTrack);

            if ($providerPathnames) {
                $pathnames[$provider->getName()] = $providerPathnames;
            }
        }

        return $pathnames;
    }

    /**
     * Zwraca liczbę utworów wskazanych przez dostawców kandydatów, które trafiły do zbioru kandydatów,
     * oraz liczbę tych z nich, które odrzucił próg. Pusta lista oznacza brak zgłoszeń, co obejmuje
     * również przypadek niedostępnej kolekcji Musly.
     *
     * @param Track[] $candidates
     * @param SimilarTracks[] $similarTracks
     * @return int[]
     */
    private function countRejectedNeighbours(Track $baseTrack, array $candidates, array $similarTracks): array
    {
        $pathnamesByProvider = $this->getCandidatePathnames($baseTrack);

        $neighbourPathnames = $pathnamesByProvider ? array_merge(...array_values($pathnamesByProvider)) : [];
        $neighbourPathnames = array_diff($neighbourPathnames, [ $baseTrack->getPathname() ]);

        $candidatePathnames = array_map(static fn (Track $candidate) => $candidate->getPathname(), $candidates);
        $resultPathnames = array_map(
            static fn (SimilarTracks $similarTrack) => $similarTrack->getSecondTrack()->getPathname(),
            $similarTracks,
        );

        $scoredNeighbours = array_intersect($neighbourPathnames, $candidatePathnames);

        return [ count($scoredNeighbours), count(array_diff($scoredNeighbours, $resultPathnames)) ];
    }

    private function writeSummary(
        OutputInterface $output,
        array $baseTracks,
        array $candidateCounts,
        array $resultCounts,
        array $candidateSimilarityValues,
    ): void {
        $table = new Table($output);
        $table->setHeaderTitle('Podsumowanie');
        $table->setHeaders([ 'Miara', 'Wartość' ]);
        $table->setRows([
            [ 'utwory bazowe w próbce', count($baseTracks) ],
            [ 'kandydaci przed progiem (mediana)', self::percentile($candidateCounts, 50) ],
            [ 'wyniki po progu i obcięciu (mediana)', self::percentile($resultCounts, 50) ],
            [ 'ocenione pary', count($candidateSimilarityValues) ],
            [ 'wynik kandydata: minimum', $candidateSimilarityValues ? min($candidateSimilarityValues) : '-' ],
            [ 'wynik kandydata: decyl dolny', self::percentile($candidateSimilarityValues, 10) ],
            [ 'wynik kandydata: mediana', self::percentile($candidateSimilarityValues, 50) ],
            [ 'wynik kandydata: decyl górny', self::percentile($candidateSimilarityValues, 90) ],
            [ 'wynik kandydata: maksimum', $candidateSimilarityValues ? max($candidateSimilarityValues) : '-' ],
        ]);
        $table->render();
    }

    private function writeProviderDistribution(OutputInterface $output, array $providerValues): void
    {
        if (!$providerValues) {
            return;
        }

        $rows = [];

        foreach ($providerValues as $providerName => $values) {
            $known = array_filter($values, static fn (?int $value) => $value !== null);
            $zeroCount = count(array_filter($known, static fn (int $value) => $value === 0));

            $rows[] = [
                $providerName,
                $known ? min($known) : '-',
                self::percentile($known, 50),
                $known ? max($known) : '-',
                $known ? sprintf('%.1f%%', 100 * $zeroCount / count($known)) : '-',
                sprintf('%.1f%%', 100 * (count($values) - count($known)) / count($values)),
            ];
        }

        $table = new Table($output);
        $table->setHeaderTitle('Rozkład wartości dostawców');
        $table->setHeaders([ 'Dostawca', 'Minimum', 'Mediana', 'Maksimum', 'Udział zer', 'Brak danych' ]);
        $table->setRows($rows);
        $table->render();
    }

    private function writeNeighbourLoss(
        OutputInterface $output,
        array $neighbourCounts,
        array $rejectedNeighbourCounts,
    ): void {
        if (!array_filter($neighbourCounts)) {
            $output->writeln('<comment>Żaden dostawca nie zgłosił kandydatów, pominięto pomiar utraty</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaderTitle('Kandydaci zgłoszeni przez dostawców');
        $table->setHeaders([ 'Miara', 'Wartość' ]);
        $table->setRows([
            [ 'utwory bazowe w próbce', count($neighbourCounts) ],
            [ 'zgłoszeni kandydaci (mediana)', self::percentile($neighbourCounts, 50) ],
            [ 'z tego odrzuconych progiem (mediana)', self::percentile($rejectedNeighbourCounts, 50) ],
            [ 'z tego odrzuconych progiem (maksimum)', max($rejectedNeighbourCounts) ],
        ]);
        $table->render();
    }

    private function writeReportFile(string $reportFile, array $rows, array $providerNames): void
    {
        $handle = fopen($reportFile, 'wb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open report file "%s" for writing', $reportFile));
        }

        fputcsv($handle, array_merge([ 'base_track', 'compared_track', 'similarity' ], $providerNames));

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    private static function percentile(array $values, int $percentile): int|string
    {
        if (!$values) {
            return '-';
        }

        $values = array_values($values);

        sort($values);

        $index = (int) floor(($percentile / 100) * (count($values) - 1));

        return $values[$index];
    }
}
