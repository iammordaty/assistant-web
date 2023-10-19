<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Traversable;

/** Lokalizacja tymczasowa / wrzucone na szybko; przemyśleć, nie przywiązywać się */
final class TrackSearchService
{
    public function __construct(private TrackRepository $trackRepository)
    {
    }

    /** Maksymalna liczba wyszukanych utworów na stronie */
    public const MAX_TRACKS_PER_PAGE = 50;

    public function findOneByName(string $name): ?Track
    {
        $searchCriteria = SearchCriteriaFacade::createFromName($name);
        $sort = Storage::SORT_TEXT_SCORE_DESC;

        $track = $this->trackRepository->getOneBy($searchCriteria, $sort);

        return $track;
    }

    public function findOneByGuid(string $guid): ?Track
    {
        $searchCriteria = SearchCriteriaFacade::createFromGuid($guid);
        $track = $this->trackRepository->getOneBy($searchCriteria);

        return $track;
    }

    public function findOneByPathname(string $pathname): ?Track
    {
        $searchCriteria = SearchCriteriaFacade::createFromPathname($pathname);
        $track = $this->trackRepository->getOneBy($searchCriteria);

        return $track;
    }

    public function findByName(string $name, ?string $sort, int $page): array
    {
        $criteria = SearchCriteriaFacade::createFromName($name);
        $sort = SearchSort::create($sort, default: SearchSort::TEXT_SCORE);

        return $this->search($criteria, $sort, $page);
    }

    public function findByFields(array $fields, ?string $sort, int $page): array
    {
        $criteria = SearchCriteriaFacade::createFromFields($fields);
        $sort = SearchSort::create($sort, default: SearchSort::ARTIST);

        return $this->search($criteria, $sort, $page);
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return Track[]|Traversable
     */
    public function findBy(
        SearchCriteria $searchCriteria,
        ?array $sort = null,
        ?int $limit = null,
        ?int $skip = null
    ): array|Traversable {
        $tracks = $this->trackRepository->findBy($searchCriteria, $sort, $limit, $skip);

        return $tracks;
    }

    public function count(SearchCriteria $searchCriteria): int
    {
        $tracks = $this->trackRepository->countBy($searchCriteria);

        return $tracks;
    }

    private function search(SearchCriteria $criteria, array $sort, int $page): array
    {
        $limit = TrackSearchService::MAX_TRACKS_PER_PAGE;
        $skip = ($page - 1) * TrackSearchService::MAX_TRACKS_PER_PAGE;

        $tracks = $this->findBy($criteria, $sort, $limit, $skip);
        $count = $this->count($criteria);

        // warto zastanowić się na zwróceniem samego paginatora co zhermetyzuje funkcjonalność
        // i odchudzi kontrolery.

        return [
            'tracks' => $tracks,
            'count' => $count,
        ];
    }
}
