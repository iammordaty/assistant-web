<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaFactory;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Request\SearchRequest;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po metadanych
 */
final readonly class AdvancedSearchController
{
    public function __construct(
        private RouteResolver $routeResolver,
        private SimilarTracksCollectionService $similarTracksCollectionService,
        private TrackSearchService $searchService,
        private Twig $view,
    ) {
    }

    /** Renderuje stronę wyszukiwania */
    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        [ $shouldRedirect, $redirectUrl ] = $this->shouldRedirectFromArtistTitleQuery($queryParams);

        if ($shouldRedirect) {
            return $response->withRedirect($redirectUrl);
        }

        $form = array_merge(SearchRequest::DEFAULTS, $queryParams);
        $isFormSubmitted = $this->isFormSubmitted($form);

        if ($isFormSubmitted) {
            $trackName = $form['track'] ?? '';

            if ($trackName) {
                $track = $this->searchService->findByName($trackName);
                $similarTracksResult = $this->similarTracksCollectionService->getSimilarTracks($track->getFile());

                $tracksPathname = array_map(
                    fn ($track) => $track->getSecondTrack()->getPathname(),
                    $similarTracksResult->getSimilarTracks()
                );

                $queryParams['pathname'] = array_values($tracksPathname);
            }

            $page = max(1, (int) ($form['page'] ?? 1));
            $sort = SearchSort::fromQueryString($form['sort'] ?? null, SearchSort::byName());

            $searchRequest = SearchRequest::fromQueryParams($queryParams);
            $result = $this->searchService->search($searchRequest->toSearchCriteria(), $sort, limit: 100, page: $page);

            $paginator = PagerfantaFactory::createWithNullAdapter(
                $result->total,
                $result->page,
                $result->limit,
            );

            if ($request->isXhr()) {
                return $this->view->render($response, '@search/common/list.twig', [
                    'routeQuery' => $form,
                    'paginator' => $paginator,
                    'routeName' => 'search.advanced.index',
                    'tracks' => $result->tracks,
                    'sort' => $sort,
                    'withTextScoreSort' => true,
                ]);
            }
        }

        return $this->view->render($response, '@search/advanced.twig', [
            'menu' => 'search',
            'form' => $form,
            'isFormSubmitted' => $isFormSubmitted,
            'paginator' => $paginator ?? null,
            'routeName' => 'search.advanced.index',
            'tracks' => isset($result) ? $result->tracks : [],
        ]);
    }

    private function isFormSubmitted(array $criteria): bool
    {
        $hasAtLeastOneValue = count(array_filter(array_values($criteria))) >= 1;

        return $hasAtLeastOneValue;
    }

    private function shouldRedirectFromArtistTitleQuery(array $queryParams): array
    {
        $artist = trim((string) ($queryParams['artist'] ?? ''));
        $title = trim((string) ($queryParams['title'] ?? ''));

        if ($artist === '' && $title === '') {
            return [ false, null ];
        }

        if (empty($queryParams['name'])) {
            $parts = [];

            if ($artist !== '') {
                $parts[] = 'artist: ' . $artist;
            }

            if ($title !== '') {
                $parts[] = 'title: ' . $title;
            }

            $queryParams['name'] = trim(implode(' ', $parts));
        }

        unset($queryParams['artist'], $queryParams['title']);

        $route = Route::create('search.advanced.index')->withQuery($queryParams);
        $redirectUrl = $this->routeResolver->resolve($route);

        return [ true, $redirectUrl ];
    }
}
