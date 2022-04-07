<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaFactory;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Search\Extension\SearchCriteriaFacade;
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
        private SimilarTracksCollectionService $similarTracksCollectionService,
        private TrackSearchService $searchService,
        private Twig $view,
    ) {
    }

    /**
     * Renderuje stronę wyszukiwania
     *
     * Ograniczanie listy znalezionych utworów poprzez similarTracksCollectionService wrzucone na szybko.
     * Na moduł wyszukiwania należałoby spojrzeć nieco szerzej:
     * @see SearchCriteriaFacade::createFromFields
     */
    public function index(Request $request, Response $response): Response
    {
        $form = array_merge(SearchCriteriaFacade::DEFAULTS, $request->getQueryParams());
        $isFormSubmitted = $this->isFormSubmitted($form);

        if ($isFormSubmitted) {
            $page = max(1, (int) ($form['page'] ?? 1));

            $trackName = $form['track'] ?? '';

            if ($trackName) {
                $track = $this->searchService->findOneByName($trackName);
                $similarTracksResult = $this->similarTracksCollectionService->getSimilarTracks($track->getFile());

                $tracksPathname = array_map(
                    fn ($track) => $track->getSecondTrack()->getPathname(),
                    $similarTracksResult->getSimilarTracks()
                );

                $form['pathname'] = array_values($tracksPathname);
            }

            [ 'count' => $count, 'tracks' => $tracks ] = $this->searchService->findByFields($form, $page);

            $paginator = PagerfantaFactory::createWithNullAdapter(
                $count,
                $page,
                TrackSearchService::MAX_TRACKS_PER_PAGE
            );
        }

        return $this->view->render($response, '@search/advanced/index.twig', [
            'menu' => 'search',
            'form' => $form,
            'isFormSubmitted' => $isFormSubmitted,
            'paginator' => $paginator ?? null,
            'routeName' => 'search.advanced.index',
            'tracks' => $tracks ?? [],
        ]);
    }

    private function isFormSubmitted(array $criteria): bool
    {
        $hasAtLeastOneValue = count(array_filter(array_values($criteria))) >= 1;

        return $hasAtLeastOneValue;
    }
}
