<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;

final readonly class SimilarPublisherChecker implements CheckerInterface
{
    use SimilarityDetection;

    public function __construct(
        private TrackRepository $trackRepository,
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
        $publisherCounts = [];

        foreach ($this->trackRepository->findBy(new SearchCriteria()) as $track) {
            $publisher = $track->getPublisher();

            if ($publisher !== null && $publisher !== '') {
                $publisherCounts[$publisher] = ($publisherCounts[$publisher] ?? 0) + 1;
            }
        }

        return $this->findSimilarPairs(
            $this->getCategory(),
            'similar_publisher',
            $publisherCounts,
            $this->slugifyService,
        );
    }
}
