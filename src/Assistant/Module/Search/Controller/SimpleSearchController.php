<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaFactory;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po nazwie lub artyście
 */
final readonly class SimpleSearchController
{
    public function __construct(
        private RouteResolver $routeResolver,
        private TrackSearchService $searchService,
        private Twig $view,
    ) {
    }

    /** Renderuje stronę wyszukiwania */
    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $form = $request->getQueryParams();
        $isFormSubmitted = $this->isFormSubmitted($form);

        if ($isFormSubmitted) {
            $name = $form['name'];

            if (str_contains($name, ': ')) {
                $route = Route::create('search.advanced.index')->withQuery([ 'name' => $name ]);
                $redirectUrl = $this->routeResolver->resolve($route);

                return $response->withRedirect($redirectUrl);
            }
            
            $criteria = SearchCriteriaFacade::createFromName($name);

            $page = max(1, (int) ($form['page'] ?? 1));
            $sort = SearchSort::fromQueryString($form['sort'] ?? null, SearchSort::byTextScore());

            $result = $this->searchService->search($criteria, $sort, limit: 100, page: $page);
            $paginator = PagerfantaFactory::createWithNullAdapter($result->total, $result->page, $result->limit);

            if ($request->isXhr()) {
                return $this->view->render($response, '@search/common/list.twig', [
                    'routeQuery' => $form,
                    'paginator' => $paginator,
                    'routeName' => 'search.simple.index',
                    'tracks' => $result->tracks,
                    'sort' => $sort,
                    'withTextScoreSort' => true,
                ]);
            }

            if ($result->total === 1) {
                $track = is_array($result->tracks)
                    ? $result->tracks[0]
                    : iterator_to_array($result->tracks)[0];

                $route = Route::create('track.track.index', [ 'guid' => $track->getGuid() ]);
                $redirectUrl = $this->routeResolver->resolve($route);

                return $response->withRedirect($redirectUrl);
            }
        }

        return $this->view->render($response, '@search/simple.twig', [
            'menu' => 'search',
            'form' => $form,
            'isFormSubmitted' => $isFormSubmitted,
            'paginator' => $paginator ?? null,
            'routeName' => 'search.simple.index',
            'tracks' => $result?->tracks ?? [],
        ]);
    }

    private function isFormSubmitted(array $form): bool
    {
        return !empty($form['name']);
    }
}
