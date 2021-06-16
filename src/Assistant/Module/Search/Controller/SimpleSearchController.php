<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Model\Track;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po nazwie lub artyście
 */
final class SimpleSearchController
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
            $name = $form['query'];
            $page = max(1, (int) ($form['page'] ?? 1));

            $results = $this->searchService->findByName($name, $page);

            if ($results['count'] > TrackSearchService::MAX_TRACKS_PER_PAGE) {
                $routeParser = RouteContext::fromRequest($request)->getRouteParser();

                $routeGenerator = function ($page) use ($form, $routeParser) {
                    $routeName = 'search.simple.index';
                    $data = [];
                    $paginatorQueryParams = [ 'query' => str_replace('-', ' ', $form['query']) ];

                    $baseUrl = $routeParser->urlFor($routeName, $data, $paginatorQueryParams);
                    $url = sprintf('%s&page=%d', $baseUrl, $page);

                    return $url;
                };

                $paginator = $this->searchService->getPaginator($page, $results['count'], $routeGenerator);
            }
        }

        if ($results['count'] === 1) {
            /** @var Track $track */
            $track = $results['tracks']->current();

            $routeName = 'track.track.index';
            $data = [ 'guid' => $track->getGuid() ];
            $queryParams = [ ];

            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $redirectUrl = $routeParser->urlFor($routeName, $data, $queryParams);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(302);

            return $redirect;
        }

        return $this->view->render($response, '@search/simple/index.twig', [
            'menu' => 'search',
            'form' => $form,
            'result' => $results,
            'paginator' => $paginator,
        ]);
    }

    private function isRequestValid(array $criteria): bool
    {
        return !empty($criteria) === true;
    }
}
