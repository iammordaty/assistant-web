<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Search\Extension\TrackSearchService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po metadanych
 */
final class AdvancedSearchController
{
    public function __construct(
        private RouteResolver $routeResolver,
        private TrackSearchService $searchService,
        private Twig $view,
    ) {
    }

    /**
     * Renderuje stronę wyszukiwania
     */
    public function index(Request $request, Response $response): Response
    {
        $form = $request->getQueryParams();

        $results = [ 'count' => 0 ];
        $paginator = null;

        if ($this->isRequestValid($form)) {
            $page = max(1, (int) ($form['page'] ?? 1));
            $results = $this->searchService->findByFields($form, $page);

            if ($results['count'] > TrackSearchService::MAX_TRACKS_PER_PAGE) {
                $route = Route::create('search.advanced.index')->withQuery($form);
                $baseUrl = $this->routeResolver->resolve($route);

                $paginator = $this->searchService->getPaginator(
                    $page,
                    $results['count'],
                    fn($page) => sprintf('%s&page=%d', $baseUrl, $page)
                );
            }
        }

        return $this->view->render($response, '@search/advanced/index.twig', [
            'menu' => 'search',
            'form' => $form,
            'result' => $results,
            'paginator' => $paginator,
        ]);
    }

    private function isRequestValid(array $criteria): bool
    {
        return !empty($criteria) || filter_input(INPUT_GET, 'submit', FILTER_VALIDATE_BOOLEAN) === true;
    }
}
