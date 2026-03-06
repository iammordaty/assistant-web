<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Collection\Extension\Analysis\CheckerInterface;
use Assistant\Module\Collection\Extension\Analysis\IncompleteDataChecker;
use Assistant\Module\Collection\Extension\Analysis\IntegrityChecker;
use Assistant\Module\Collection\Extension\Analysis\PotentialDuplicateChecker;
use Assistant\Module\Collection\Extension\Analysis\SimilarArtistChecker;
use Assistant\Module\Collection\Extension\Analysis\SimilarGenreChecker;
use Assistant\Module\Collection\Extension\Analysis\SimilarPublisherChecker;
use Assistant\Module\Collection\Repository\CollectionAnalysisRepository;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Track\Repository\TrackStatsRepository;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CollectionAnalysisTask extends AbstractTask
{
    protected static $defaultName = 'collection:analyze';

    /** @param CheckerInterface[] $checkers */
    public function __construct(
        Logger $logger,
        private array $checkers,
        private CollectionAnalysisRepository $analysisRepository,
        private TrackRepository $trackRepository,
        private TrackStatsRepository $statsRepository,
        private DirectoryRepository $directoryRepository,
    ) {
        parent::__construct($logger);
    }

    public static function factory(ContainerInterface $container): self
    {
        $config = $container->get(Config::class);
        $trackRepository = $container->get(TrackRepository::class);
        $statsRepository = $container->get(TrackStatsRepository::class);
        $slugifyService = $container->get(SlugifyService::class);
        $similarTracksCollectionService = $container->get(SimilarTracksCollectionService::class);

        $checkers = [
            new IntegrityChecker($trackRepository, $similarTracksCollectionService, $config),
            new IncompleteDataChecker($trackRepository, $statsRepository),
            new SimilarArtistChecker($statsRepository, $slugifyService, $config),
            new SimilarGenreChecker($statsRepository, $slugifyService),
            new SimilarPublisherChecker($trackRepository, $slugifyService),
            new PotentialDuplicateChecker($trackRepository, $slugifyService),
        ];

        return new self(
            $container->get(Logger::class),
            $checkers,
            $container->get(CollectionAnalysisRepository::class),
            $trackRepository,
            $statsRepository,
            $container->get(DirectoryRepository::class),
        );
    }

    protected function configure(): void
    {
        $this->setDescription('Analyzes collection integrity, metadata quality, and detects duplicates');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Task executed');

        $issues = [];

        foreach ($this->checkers as $checker) {
            $category = $checker->getCategory();

            $this->logger->debug('Running checker', ['category' => $category->value]);

            $checkerIssues = $checker->check();
            $issues = array_merge($issues, $checkerIssues);

            $this->logger->debug('Checker finished', [
                'category' => $category->value,
                'issues_found' => count($checkerIssues),
            ]);
        }

        $summary = $this->buildSummary();

        $this->analysisRepository->saveAnalysis($summary, $issues);

        $this->logger->info('Task finished', [
            'total_issues' => count($issues),
            'summary' => $summary,
        ]);

        return self::SUCCESS;
    }

    private function buildSummary(): array
    {
        $genreCounts = $this->statsRepository->getTrackCountByGenre();
        $artistCounts = $this->statsRepository->getTrackCountByArtist();

        return [
            'tracks_in_db' => $this->trackRepository->countBy(new SearchCriteria()),
            'directories_count' => $this->directoryRepository->countBy(new SearchCriteria()),
            'genres_count' => count($genreCounts),
            'artists_count' => count($artistCounts),
        ];
    }
}
