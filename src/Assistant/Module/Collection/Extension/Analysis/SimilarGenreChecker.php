<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Track\Repository\TrackStatsRepository;

final readonly class SimilarGenreChecker implements CheckerInterface
{
    use SimilarityDetection;

    public function __construct(
        private TrackStatsRepository $statsRepository,
        private SlugifyService $slugifyService,
    ) {
    }

    public function getCategory(): AnalysisCategory
    {
        return AnalysisCategory::METADATA;
    }

    /** @return AnalysisIssue[] */
    public function check(): array
    {
        $genreCounts = $this->statsRepository->getTrackCountByGenre();

        return $this->findSimilarPairs(
            $this->getCategory(),
            'similar_genre',
            $genreCounts,
            $this->slugifyService,
        );
    }
}
