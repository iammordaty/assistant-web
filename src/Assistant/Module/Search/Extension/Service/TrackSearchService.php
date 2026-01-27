<?php

namespace Assistant\Module\Search\Extension\Service;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Result\TrackSearchResult;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use DateTime;
use Generator;

final class TrackSearchService
{
    public function __construct(
        private RandomTrackListGenerator $randomTrackListGenerator,
        private TrackRepository $trackRepository,
    ) {
    }

    public function findByName(string $name): ?Track
    {
        return $this->findOne(
            SearchCriteriaFacade::createFromName($name),
            SearchSort::byTextScore(),
        );
    }

    public function findOne(SearchCriteria $criteria, ?SearchSort $searchSort = null): ?Track
    {
        return $this->trackRepository->getOneBy($criteria, $searchSort);
    }

    public function search(
        SearchCriteria $criteria,
        ?SearchSort $sort = null,
        ?int $limit = null,
        ?int $page = 1,
    ): TrackSearchResult {
        $page = max(1, $page);
        $offset = $limit !== null ? ($page - 1) * $limit : null;

        $tracks = $this->trackRepository->findBy($criteria, $sort, $limit, $offset);

        $trackSearchResult = new TrackSearchResult(
            tracks: $tracks,
            total: $this->count($criteria),
            page: $page,
            limit: $limit,
        );

        return $trackSearchResult;
    }

    public function count(SearchCriteria $criteria): int
    {
        return $this->trackRepository->countBy($criteria);
    }

    /** @return Generator<int, Track> */
    public function findByDirectory(Directory $directory): Generator
    {
        $criteria = SearchCriteriaFacade::createFromParent($directory->getGuid());

        return $this->trackRepository->findBy($criteria, SearchSort::byName());
    }

    /** @return Track[] */
    public function getRandom(int $limit = PHP_INT_MAX): array
    {
        $generator = $this->randomTrackListGenerator;

        return $generator($limit);
    }

    /** @return Generator<int, Track> */
    public function findRecent(?DateTime $minIndexedDate = null, ?int $limit = null): Generator
    {
        $minIndexedDate ??= (new DateTime())->modify('-3 years first day of january');
        $criteria = SearchCriteriaFacade::createFromMinIndexedDate($minIndexedDate);

        return $this->trackRepository->findBy($criteria, SearchSort::byMostRecentlyIndexed(), $limit);
    }
}
