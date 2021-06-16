<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Repository\Storage;
use Assistant\Module\Track\Extension\TrackService;
use Pagerfanta\Adapter\NullAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\TwitterBootstrap3View;

/** Lokalizacja tymczasowa / wrzucone na szybko; przemyśleć, nie przywiązywać się */
final class TrackSearchService
{
    public function __construct(
        private SlugifyService $slugify,
        private TrackService $trackService,
    ) {
    }

    /**
     * Maksymalna liczba wyszukanych utworów na stronie
     *
     * @var int
     */
    public const MAX_TRACKS_PER_PAGE = 50;

    public function findByName(string $name, int $page): array
    {
        // slugify w tym miejscu (w sumie cała metoda) wrzucona trochę na szybko, nie przywiązywać się,
        // przemyśleć później jak to rozwiązać bardziej elegancko.

        $name = $this->slugify->slugify($name);

        $searchCriteria = SearchCriteriaFacade::createFromName($name);
        $tracks = $this->findBy($searchCriteria, $page);

        return $tracks;
    }

    public function findBy(SearchCriteria $criteria, int $page): array
    {
        $sort = [ 'guid' => Storage::SORT_ASC ]; // docelowo jako parametr przychodzący z frontu
        $limit = TrackSearchService::MAX_TRACKS_PER_PAGE;
        $skip = ($page - 1) * TrackSearchService::MAX_TRACKS_PER_PAGE;

        $tracks = $this->trackService->findBy($criteria, $sort, $limit, $skip);
        $count = $this->trackService->count($criteria);

        return [
            'tracks' => $tracks,
            'count' => $count,
        ];
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
}
