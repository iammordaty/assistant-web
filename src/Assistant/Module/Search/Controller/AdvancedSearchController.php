<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Search\Extension\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\TrackSearchService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po metadanych
 */
final class AdvancedSearchController
{
    public function __construct(
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

            $searchCriteria = SearchCriteriaFacade::createFromSearchRequest($request);
            $results = $this->searchService->findBy($searchCriteria, $page);

            if ($results['count'] > TrackSearchService::MAX_TRACKS_PER_PAGE) {
                $routeParser = RouteContext::fromRequest($request)->getRouteParser();

                $routeGenerator = function ($page) use ($form, $routeParser) {
                    $routeName = 'search.advanced.index';
                    $data = [];
                    $paginatorQueryParams = $form;

                    $baseUrl = $routeParser->urlFor($routeName, $data, $paginatorQueryParams);
                    $url = sprintf('%s&page=%d', $baseUrl, $page);

                    return $url;
                };

                $paginator = $this->searchService->getPaginator($page, $results['count'], $routeGenerator);
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
