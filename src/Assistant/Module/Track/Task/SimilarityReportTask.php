<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Similarity;
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
 * Raport odpowiada na pytania, których nie widać w interfejsie: jak rozłożone są wyniki końcowe,
 * ilu kandydatów odrzuca próg, jaki rozkład wartości ma każdy dostawca oraz ilu sąsiadów wskazanych
 * przez Musly odpada na filtrach metadanych. Bez tych liczb strojenie wag i progu jest zgadywaniem.
 *
 * Próbka pochodzi z cache'owanej listy losowych utworów, więc dwa przebiegi obejmują te same utwory
 * bazowe i dają się porównać.
 */
final class SimilarityReportTask extends AbstractTask
{
    private const int DEFAULT_SAMPLE_SIZE = 25;

    protected static $defaultName = 'track:similarity-report';

    public function __construct(
        Logger $logger,
        private SimilarityBuilder $similarityBuilder,
        private TrackSearchService $searchService,
        private SimilarTracksCollectionService $similarTracksCollectionService,
    ) {
        parent::__construct($logger);
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(SimilarityBuilder::class),
            $container->get(TrackSearchService::class),
            $container->get(SimilarTracksCollectionService::class),
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

        $similarityService = $this->similarityBuilder->createService()->getSimilarityService();

        $similarityValues = [];
        $candidateCounts = [];
        $resultCounts = [];
        $providerValues = [];
        $neighboursOutsideWindow = [];
        $rows = [];

        foreach ($baseTracks as $baseTrack) {
            $candidateGuids = $this->getCandidateGuids($similarityService, $baseTrack);
            $candidateCounts[] = count($candidateGuids);

            $similarTracks = $similarityService->getSimilarTracks($baseTrack);
            $resultCounts[] = count($similarTracks);

            /** @var SimilarTracks $similarTrack */
            foreach ($similarTracks as $similarTrack) {
                $comparedTrack = $similarTrack->getSecondTrack();
                $similarityValue = (int) round($similarTrack->getSimilarityValue());

                $similarityValues[] = $similarityValue;

                $values = $similarityService->getProviderSimilarityValues($baseTrack, $comparedTrack);

                foreach ($values as $providerName => $value) {
                    $providerValues[$providerName][] = $value;
                }

                $rows[] = array_merge(
                    [ $baseTrack->getGuid(), $comparedTrack->getGuid(), $similarityValue ],
                    array_values($values),
                );
            }

            $outsideWindow = $this->countNeighboursOutsideCandidates($baseTrack, $candidateGuids);

            if ($outsideWindow !== null) {
                $neighboursOutsideWindow[] = $outsideWindow;
            }
        }

        $this->writeSummary($output, $baseTracks, $candidateCounts, $resultCounts, $similarityValues);
        $this->writeProviderDistribution($output, $providerValues);
        $this->writeNeighbourLoss($output, $neighboursOutsideWindow);

        $reportFile = $input->getOption('report-file');

        if ($reportFile) {
            $this->writeReportFile($reportFile, $rows, array_keys($providerValues));

            $output->writeln(sprintf('Per-pair values written to <info>%s</info>', $reportFile));
        }

        $this->logger->debug('Task finished');

        return self::SUCCESS;
    }

    /**
     * Identyfikatory utworów spełniających kryteria metadanych, czyli zbiór kandydatów
     * przed punktowaniem i przed odcięciem progiem.
     *
     * @return string[]
     */
    private function getCandidateGuids(Similarity $similarityService, Track $baseTrack): array
    {
        $criteria = $similarityService->getSimilarityCriteria($baseTrack);
        $result = $this->searchService->search($criteria, limit: null);

        $guids = [];

        foreach ($result->tracks as $track) {
            $guids[] = $track->getGuid();
        }

        return $guids;
    }

    /**
     * Liczba sąsiadów wskazanych przez Musly, którzy nie przeszli filtrów metadanych, czyli nie mieli
     * szansy pojawić się w wyniku. Zwraca null, gdy lista sąsiadów jest niedostępna.
     */
    private function countNeighboursOutsideCandidates(Track $baseTrack, array $candidateGuids): ?int
    {
        try {
            $neighbours = $this->similarTracksCollectionService->getSimilarTracks($baseTrack->getFile());
        } catch (SimilarTracksCollectionException $e) {
            $this->logger->warning('Neighbour list unavailable', [
                'track' => $baseTrack->getGuid(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $pathnames = array_keys($neighbours->getSimilarTracks());

        if (!$pathnames) {
            return 0;
        }

        $result = $this->searchService->search(new SearchCriteria(pathname: $pathnames), limit: null);

        $outsideWindow = 0;

        foreach ($result->tracks as $track) {
            if ($track->getGuid() === $baseTrack->getGuid()) {
                continue;
            }

            if (!in_array($track->getGuid(), $candidateGuids, true)) {
                $outsideWindow++;
            }
        }

        return $outsideWindow;
    }

    private function writeSummary(
        OutputInterface $output,
        array $baseTracks,
        array $candidateCounts,
        array $resultCounts,
        array $similarityValues,
    ): void {
        $table = new Table($output);
        $table->setHeaderTitle('Podsumowanie');
        $table->setHeaders([ 'Miara', 'Wartość' ]);
        $table->setRows([
            [ 'utwory bazowe w próbce', count($baseTracks) ],
            [ 'kandydaci po kryteriach (mediana)', self::percentile($candidateCounts, 50) ],
            [ 'wyniki po progu i obcięciu (mediana)', self::percentile($resultCounts, 50) ],
            [ 'ocenione pary', count($similarityValues) ],
            [ 'wynik końcowy: minimum', $similarityValues ? min($similarityValues) : '-' ],
            [ 'wynik końcowy: decyl dolny', self::percentile($similarityValues, 10) ],
            [ 'wynik końcowy: mediana', self::percentile($similarityValues, 50) ],
            [ 'wynik końcowy: decyl górny', self::percentile($similarityValues, 90) ],
            [ 'wynik końcowy: maksimum', $similarityValues ? max($similarityValues) : '-' ],
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
            $zeroCount = count(array_filter($values, static fn (int $value) => $value === 0));

            $rows[] = [
                $providerName,
                min($values),
                self::percentile($values, 50),
                max($values),
                sprintf('%.1f%%', 100 * $zeroCount / count($values)),
            ];
        }

        $table = new Table($output);
        $table->setHeaderTitle('Rozkład wartości dostawców');
        $table->setHeaders([ 'Dostawca', 'Minimum', 'Mediana', 'Maksimum', 'Udział zer' ]);
        $table->setRows($rows);
        $table->render();
    }

    private function writeNeighbourLoss(OutputInterface $output, array $neighboursOutsideWindow): void
    {
        if (!$neighboursOutsideWindow) {
            $output->writeln('<comment>Lista sąsiadów Musly niedostępna, pominięto pomiar utraty</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaderTitle('Sąsiedzi Musly poza oknem metadanych');
        $table->setHeaders([ 'Miara', 'Wartość' ]);
        $table->setRows([
            [ 'utwory bazowe z dostępną listą', count($neighboursOutsideWindow) ],
            [ 'odrzuceni przez filtry (mediana)', self::percentile($neighboursOutsideWindow, 50) ],
            [ 'odrzuceni przez filtry (maksimum)', max($neighboursOutsideWindow) ],
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

        sort($values);

        $index = (int) floor(($percentile / 100) * (count($values) - 1));

        return $values[$index];
    }
}
