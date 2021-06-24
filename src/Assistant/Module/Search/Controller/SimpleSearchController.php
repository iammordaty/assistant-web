<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Redirect;
use Assistant\Module\Common\Extension\UrlFactory;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Model\Track;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
                $baseUrl = UrlFactory::fromRequest($request)
                    ->setRouteName('search.simple.index')
                    ->setQueryParams([ 'query' => str_replace('-', ' ', $form['query']) ]);

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

            $redirect = Redirect::create(
                request: $request,
                routeName: 'track.track.index',
                data: [ 'guid' => $track->getGuid() ],
            );

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
