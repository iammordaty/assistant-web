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
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;
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

    public function index(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackService->getByGuid($guid);

        if (!$track) {
            $route = Route::create('search.simple.index')->withQuery([ 'query' => str_replace('-', ' ', $guid) ]);

            $redirect = $response
                ->withRedirect($this->routeResolver->resolve($route))
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

            return $redirect;
        }

        $form = SimilarityParametersForm::create($track->toDto(), $request);

        $similarTracks = $this->similarityBuilder
            ->withTrack($track)
            ->withForm($form)
            ->createService()
            ->getSimilarTracks();

        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return $this->view->render($response, '@track/similarTracks/list.twig', [
                'similarTracksList' => $similarTracks,
                'similarTracksSoftLimit' => self::SIMILAR_TRACKS_SOFT_LIMIT,
            ]);
        }

        $pathBreadcrumbs = $this->pathBreadcrumbs->get(dirname($track->getPathname()));

        return $this->view->render($response, '@track/index.twig', [
            'menu' => 'track',
            'track' => $track,
            'musicalKeyInfo' => $this->getTrackMusicalKeyInfo($track),
            'pathBreadcrumbs' => $pathBreadcrumbs,
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
