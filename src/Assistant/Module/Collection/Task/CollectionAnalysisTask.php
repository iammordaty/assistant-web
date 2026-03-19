<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Collection\Extension\Analysis\AnalysisIssue;
use Assistant\Module\Collection\Extension\Analysis\CheckerInterface;
use Assistant\Module\Collection\Extension\Analysis\IncompleteDataChecker;
use Assistant\Module\Collection\Extension\Analysis\IntegrityChecker;
use Assistant\Module\Collection\Extension\Analysis\LowBitrateChecker;
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
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Track\Model\Track;
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
            new LowBitrateChecker($trackRepository),
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

        $this->logger->debug('Enriching issues with track and file data');
        $issues = $this->enrichIssues($issues);

        $summary = $this->buildSummary();

        $this->analysisRepository->saveAnalysis($summary, $issues);

        $this->logger->info('Task finished', [
            'total_issues' => count($issues),
            'summary' => $summary,
        ]);

        return self::SUCCESS;
    }

    /** @param AnalysisIssue[] $issues @return AnalysisIssue[] */
    private function enrichIssues(array $issues): array
    {
        $guids = $this->collectGuids($issues);
        $tracks = $this->loadTracksByGuids($guids);

        $enriched = [];

        foreach ($issues as $issue) {
            $enriched[] = $this->enrichSingleIssue($issue, $tracks);
        }

        return $enriched;
    }

    private function collectGuids(array $issues): array
    {
        $guids = [];

        foreach ($issues as $issue) {
            foreach (['guid', 'guid_a', 'guid_b'] as $key) {
                if (isset($issue->details[$key])) {
                    $guids[$issue->details[$key]] = true;
                }
            }
        }

        return array_keys($guids);
    }

    /** @return array<string, Track> */
    private function loadTracksByGuids(array $guids): array
    {
        $map = [];

        foreach ($guids as $guid) {
            $track = $this->trackRepository->getOneBy(
                SearchCriteriaFacade::createFromGuid($guid)
            );

            if ($track) {
                $map[$guid] = $track;
            }
        }

        $this->logger->debug('Loaded tracks for enrichment', ['count' => count($map)]);

        return $map;
    }

    private function enrichSingleIssue(AnalysisIssue $issue, array $tracks): AnalysisIssue
    {
        $details = $issue->details;

        if (isset($details['guid']) && isset($tracks[$details['guid']])) {
            $track = $tracks[$details['guid']];
            $details['track'] = $this->buildTrackInfo($track);
            $details['file'] = $this->buildFileInfo($track);
        }

        if (isset($details['guid_a']) && isset($tracks[$details['guid_a']])) {
            $track = $tracks[$details['guid_a']];
            $details['track_a'] = $this->buildTrackInfo($track);
            $details['file_a'] = $this->buildFileInfo($track);
        }

        if (isset($details['guid_b']) && isset($tracks[$details['guid_b']])) {
            $track = $tracks[$details['guid_b']];
            $details['track_b'] = $this->buildTrackInfo($track);
            $details['file_b'] = $this->buildFileInfo($track);
        }

        $details = $this->enrichSimilarityIssue($issue->type, $details);

        return new AnalysisIssue($issue->category, $issue->type, $details, $issue->ignored);
    }

    private function enrichSimilarityIssue(string $type, array $details): array
    {
        $fieldMap = [
            'similar_artist' => 'artist',
            'similar_publisher' => 'publishers',
            'similar_genre' => 'genres',
        ];

        if (!isset($fieldMap[$type])) {
            return $details;
        }

        $field = $fieldMap[$type];
        $maxCountForTrackDisplay = 2;

        foreach (['a', 'b'] as $side) {
            $count = $details["count_{$side}"] ?? 0;

            if ($count > $maxCountForTrackDisplay) {
                continue;
            }

            $value = $details["value_{$side}"] ?? null;

            if ($value === null) {
                continue;
            }

            $criteria = match ($field) {
                'artist' => new SearchCriteria(artist: $value),
                'publishers' => new SearchCriteria(publishers: [$value]),
                'genres' => new SearchCriteria(genres: [$value]),
            };

            $foundTracks = iterator_to_array($this->trackRepository->findBy($criteria, limit: $maxCountForTrackDisplay));
            $tracksData = [];

            foreach ($foundTracks as $track) {
                $tracksData[] = [
                    'track' => $this->buildTrackInfo($track),
                    'file' => $this->buildFileInfo($track),
                ];
            }

            $details["tracks_{$side}"] = $tracksData;
        }

        return $details;
    }

    private function buildTrackInfo(Track $track): array
    {
        return [
            'guid' => $track->getGuid(),
            'name' => $track->getName(),
            'artist' => $track->getArtist(),
            'title' => $track->getTitle(),
            'genre' => $track->getGenre(),
            'album' => $track->getAlbum(),
            'publisher' => $track->getPublisher(),
            'year' => $track->getYear(),
            'bpm' => $track->getBpm(),
            'initial_key' => $track->getInitialKey(),
            'length' => $track->getLength(),
            'pathname' => $track->getPathname(),
        ];
    }

    private function buildFileInfo(Track $track): array
    {
        if (!is_readable($track->getPathname())) {
            return [];
        }

        $file = $track->getFile();

        return [
            'size' => $file->getSize(),
            'bitrate' => $file->getBitrate(),
            'sample_rate' => $file->getSampleRate(),
            'channel_mode' => $file->getChannelMode(),
        ];
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
