<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaFactory;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Model\Track;
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

    /**
     * Renderuje stronę wyszukiwania
     */
    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $form = $request->getQueryParams();
        $isFormSubmitted = $this->isFormSubmitted($form);

        if ($isFormSubmitted) {
            $name = $form['query'];
            $page = max(1, (int) ($form['page'] ?? 1));
            $sort = $form['sort'] ?? null;

            [ 'count' => $count, 'tracks' => $tracks ] = $this->searchService->findByName($name, $sort, $page);

            $paginator = PagerfantaFactory::createWithNullAdapter(
                $count,
                $page,
                TrackSearchService::MAX_TRACKS_PER_PAGE
            );

            if ($request->isXhr()) {
                return $this->view->render($response, '@search/common/list.twig', [
                    'routeQuery' => $form,
                    'paginator' => $paginator,
                    'routeName' => 'search.simple.index',
                    'tracks' => $tracks,
                    'sort' => $form['sort'],
                    'withTextScoreSort' => true,
                ]);
            }

            if ($paginator->count() === 1) {
                /** @var Track $track */
                $track = $tracks->current();

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
            'tracks' => $tracks ?? [],
        ]);
    }

    private function isFormSubmitted(array $form): bool
    {
        return !empty($form['query']);
    }
}
