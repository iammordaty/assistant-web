<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaFactory;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Request\SearchRequest;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final readonly class SimpleSearchController
{
    private const int LIMIT = 100;

    private const array TEMPLATE_VARS = [
        'menu' => 'search',
        'form' => null,
        'isFormSubmitted' => false,
        'paginator' => null,
        'routeName' => 'search.simple.index',
        'tracks' => [],
    ];

    public function __construct(
        private RouteResolver $routeResolver,
        private TrackSearchService $searchService,
        private Twig $view,
    ) {
    }

    /** Renderuje stronę wyszukiwania */
    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $searchRequest = SearchRequest::fromServerRequest($request);

        $form = $searchRequest->getForm();
        $isFormSubmitted = $searchRequest->isFormSubmitted();

        if (!$isFormSubmitted) {
            return $this->view->render($response, '@search/simple.twig', [ ...self::TEMPLATE_VARS, ...[
                'form' => $form,
            ] ]);
        }

        if ($searchRequest->hasNameModifiers()) {
            $route = Route::create('search.advanced.index')->withQuery([ 'name' => $form['name']]);
            $redirectUrl = $this->routeResolver->resolve($route);

            return $response->withRedirect($redirectUrl);
        }

        $criteria = SearchCriteriaFacade::createFromName($form['name']);

        $page = max(1, (int) ($form['page'] ?? 1));
        $sort = SearchSort::fromQueryString($form['sort'] ?? null, SearchSort::byTextScore());

        $result = $this->searchService->search($criteria, $sort, limit: self::LIMIT, page: $page);
        $paginator = PagerfantaFactory::createWithTrackSearchResult($result);

        if ($request->isXhr()) {
            return $this->view->render($response, '@search/simple.twig', [ ...self::TEMPLATE_VARS, ...[
                'routeQuery' => $form,
                'paginator' => $paginator,
                'tracks' => $result->tracks,
                'sort' => $sort,
                'withTextScoreSort' => !$searchRequest->hasNameModifiers(),
            ] ]);
        }

        if ($result->total === 1) {
            $track = iterator_to_array($result->tracks)[0];

            $route = Route::create('track.track.index', [ 'guid' => $track->getGuid() ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            return $response->withRedirect($redirectUrl);
        }

        return $this->view->render($response, '@search/advanced.twig', [ ...self::TEMPLATE_VARS, ...[
            'form' => $form,
            'isFormSubmitted' => true,
            'paginator' => $paginator,
            'tracks' => $result->tracks,
        ] ]);
    }
}
