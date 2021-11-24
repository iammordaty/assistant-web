<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Pagerfanta\Adapter\NullAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\TwitterBootstrap3View;
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
        $track = $this->trackRepository->getOneBy($searchCriteria);

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

    public function findByName(string $name, int $page): array
    {
        $criteria = SearchCriteriaFacade::createFromName($name);
        $sort = Storage::SORT_TEXT_SCORE_DESC;

        return $this->search($criteria, $sort, $page);
    }

    public function findByFields(array $fields, int $page): array
    {
        $criteria = SearchCriteriaFacade::createFromFields($fields);
        $sort = [ 'guid' => Storage::SORT_ASC ]; // docelowo jako parametr przychodzący z frontu

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
        $tracks = $this->trackRepository->getBy($searchCriteria, $sort, $limit, $skip);

        return $tracks;
    }

    public function count(SearchCriteria $searchCriteria): int
    {
        $tracks = $this->trackRepository->countBy($searchCriteria);

        return $tracks;
    }

    /**
     * Zwraca obiekt paginatora lub null, jeśli paginator nie jest wymagany
     *
     * 24.05.2021. To jest trochę głupie, bo gdyby paginator był zwracany zawsze, uproszczony byłby widok
     * (nie trzeba byłoby przekazywać struktur: results.tracks i results.count). W podobny sposób
     * upraszcza to logikę w SimpleSearchController::index (if ($results['count'] === 1) {)
     *
     * @see MAX_TRACKS_PER_PAGE
     *
     * @param int $pageNo
     * @param int $totalCount
     * @param callable $routeGenerator
     * @return Pagerfanta|null
     */
    public function getPaginator(int $pageNo, int $totalCount, callable $routeGenerator): ?string
    {
        if ($totalCount <= TrackSearchService::MAX_TRACKS_PER_PAGE) {
            return null;
        }

        $paginator = new Pagerfanta(new NullAdapter($totalCount));
        $paginator->setMaxPerPage(TrackSearchService::MAX_TRACKS_PER_PAGE);

        try {
            $paginator->setCurrentPage($pageNo);
        } catch (NotValidCurrentPageException $e) {
            $paginator = null;

            unset($e);
        }

        if ($paginator === null) {
            return null;
        }

        return (new TwitterBootstrap3View())->render($paginator, $routeGenerator, [
            'proximity' => 2,
            'previous_message' => 'Poprzednia',
            'next_message' => 'Następna',
        ]);
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
