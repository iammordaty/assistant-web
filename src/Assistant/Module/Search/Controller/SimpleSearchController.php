<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Model\Track;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;
use Slim\Views\Twig;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po nazwie lub artyście
 */
final class SimpleSearchController
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
    public function index(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $form = $request->getQueryParams();

        $results = [ 'count' => 0 ];
        $paginator = null;

        if ($this->isRequestValid($form)) {
            $name = $form['query'];
            $page = max(1, (int) ($form['page'] ?? 1));

            $results = $this->searchService->findByName($name, $page);

            if ($results['count'] > TrackSearchService::MAX_TRACKS_PER_PAGE) {
                $route = Route::create('search.simple.index')->withQuery([ 'query' => str_replace('-', ' ', $name) ]);
                $baseUrl = $this->routeResolver->resolve($route);

                $paginator = $this->searchService->getPaginator(
                    $page,
                    $results['count'],
                    fn($page) => sprintf('%s&page=%d', $baseUrl, $page)
                );
            }
        }

        if ($results['count'] === 1) {
            /** @var Track $track */
            $track = $results['tracks']->current();
            $route = Route::create('track.track.index', [ 'guid' => $track->getGuid() ]);

            $redirect = $response
                ->withRedirect($this->routeResolver->resolve($route))
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

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
