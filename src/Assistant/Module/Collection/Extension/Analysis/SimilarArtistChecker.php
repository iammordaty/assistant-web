<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Track\Repository\TrackStatsRepository;

final readonly class SimilarArtistChecker implements CheckerInterface
{
    use SimilarityDetection;

    public function __construct(
        private TrackStatsRepository $statsRepository,
        private SlugifyService $slugifyService,
        private Config $config,
    ) {
    }

    public function getCategory(): AnalysisCategory
    {
        return AnalysisCategory::METADATA;
    }

    /** @return AnalysisIssue[] */
    public function check(): array
    {
        $artistCounts = $this->statsRepository->getTrackCountByArtist();
        $delimiters = $this->config->get('track_metadata_parser.artist.delimiters');

        return $this->findSimilarPairs(
            $this->getCategory(),
            'similar_artist',
            $artistCounts,
            $this->slugifyService,
            $delimiters,
        );
    }
}
