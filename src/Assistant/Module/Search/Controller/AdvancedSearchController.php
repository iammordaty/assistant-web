<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaFactory;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Request\SearchRequest;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final readonly class AdvancedSearchController
{
    private const int LIMIT = 100;

    private const array TEMPLATE_VARS = [
        'menu' => 'search',
        'form' => null,
        'isFormSubmitted' => false,
        'paginator' => null,
        'routeName' => 'search.advanced.index',
        'tracks' => [],
    ];

    public function __construct(
        private SimilarTracksCollectionService $similarTracksCollectionService,
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
            return $this->view->render($response, '@search/advanced.twig', [ ...self::TEMPLATE_VARS, ...[
                'form' => $form,
            ] ]);
        }

        $trackName = $form['track'] ?? '';

        if ($trackName) {
            // to co dzieje się w tym if-ie powinno leżeć w searchService->search, a pathname nie powinien
            // być sztucznie dodawany do formularza
            $track = $this->searchService->findByName($trackName);
            $similarTracksResult = $this->similarTracksCollectionService->getSimilarTracks($track->getFile());

            $tracksPathname = array_map(
                fn ($track) => $track->getSecondTrack()->getPathname(),
                $similarTracksResult->getSimilarTracks()
            );

            $tracksPathname = array_values($tracksPathname);

            $searchRequest = $searchRequest->withForm([ 'pathname' => $tracksPathname ]);
        }

        $page = max(1, (int) ($form['page'] ?? 1));
        $sort = SearchSort::fromQueryString($form['sort'] ?? null, SearchSort::byName());

        $result = $this->searchService->search($searchRequest->toSearchCriteria(), $sort, limit: self::LIMIT, page: $page);
        $paginator = PagerfantaFactory::createWithTrackSearchResult($result);

        if ($request->isXhr()) {
            return $this->view->render($response, '@search/common/list.twig', [ ...self::TEMPLATE_VARS, ...[
                'paginator' => $paginator,
                'routeQuery' => $form,
                'sort' => $sort,
                'tracks' => $result->tracks,
                'withTextScoreSort' => !$searchRequest->hasNameModifiers(),
            ] ]);
        }

        return $this->view->render($response, '@search/advanced.twig', [ ...self::TEMPLATE_VARS, ...[
            'form' => $form,
            'isFormSubmitted' => true,
            'paginator' => $paginator,
            'tracks' => $result->tracks,
        ] ]);
    }
}
