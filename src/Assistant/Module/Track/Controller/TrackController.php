<?php

namespace Assistant\Module\Track\Controller;

use Assistant\Module\Collection\Extension\MusicalKeyInfo;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\Similarity\SimilarityBuilder;
use Assistant\Module\Track\Extension\Similarity\SimilarityParametersForm;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final class TrackController
{
    private const SIMILAR_TRACKS_SOFT_LIMIT = 50;

    public function __construct(
        private PathBreadcrumbs $pathBreadcrumbs,
        private RouteResolver $routeResolver,
        private SimilarityBuilder $similarityBuilder,
        private TrackService $trackService,
        private Twig $view,
    ) {
    }

    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackService->getByGuid($guid);

        if (!$track) {
            $route = Route::create('search.simple.index')->withQuery([ 'query' => str_replace('-', ' ', $guid) ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            return $response->withRedirect($redirectUrl);
        }

        $form = SimilarityParametersForm::create($track->toDto(), $request);

        $similarTracks = $this->similarityBuilder
            ->withTrack($track)
            ->withForm($form)
            ->createService()
            ->getSimilarTracks();

        if ($request->isXhr()) {
            return $this->view->render($response, '@track/similarTracks/list.twig', [
                'similarTracksList' => $similarTracks,
                'similarTracksSoftLimit' => self::SIMILAR_TRACKS_SOFT_LIMIT,
            ]);
        }

        return $this->view->render($response, '@track/index.twig', [
            'menu' => 'track',
            'track' => $track,
            'musicalKeyInfo' => $this->getTrackMusicalKeyInfo($track),
            'pathBreadcrumbs' => $this->pathBreadcrumbs->get($track->getFile()->getPath()),
            'form' => $form,
            'similarTracksList' => $similarTracks,
            'similarTracksSoftLimit' => self::SIMILAR_TRACKS_SOFT_LIMIT,
        ]);
    }

    /** Zwraca klucze podobne do klucza podanego utworu */
    private function getTrackMusicalKeyInfo(Track $track): ?array
    {
        $musicalKeyInfo = MusicalKeyInfo::factory();

        return $musicalKeyInfo->get($track->getInitialKey());
    }
}
