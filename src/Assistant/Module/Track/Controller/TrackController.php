<?php

namespace Assistant\Module\Track\Controller;

use Assistant\Module\Collection\Extension\MusicalKeyInfo;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\Similarity;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TrackController
{
    public function __construct(
        private Config $config,
        private PathBreadcrumbs $pathBreadcrumbs,
        private RouteResolver $routeResolver,
        private TrackRepository $trackRepository,
        private TrackService $trackService,
        private Twig $view,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackService->getByGuid($guid);

        if (!$track) {
            $route = Route::create('search.simple.index')->withQuery([ 'query' => str_replace('-', ' ', $guid) ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(404);

            return $redirect;
        }

        $pathBreadcrumbs = $this->pathBreadcrumbs->get(dirname($track->getPathname()));

        $customSimilarityParameters = $request->getQueryParams()['similarity'] ?? null;

        $similarityParameters = $this->config->get('similarity');
        $similarTracks = $this->getSimilarTracks($track, $similarityParameters, $customSimilarityParameters);

        return $this->view->render($response, '@track/index.twig', [
            'menu' => 'track',
            'track' => $track,
            'keyInfo' => $this->getTrackKeyInfo($track),
            'pathBreadcrumbs' => $pathBreadcrumbs,
            'form' => $customSimilarityParameters,
            'similarTracksList' => $similarTracks,
        ]);
    }

    /**
     * Zwraca utwory podobne do podanego utworu
     *
     * @todo Przenieść do zewnętrznej klasy
     *
     * @param Track $baseTrack
     * @param array $similarityParameters
     * @param array|null $customSimilarityParameters
     * @return array
     */
    private function getSimilarTracks(
        Track $baseTrack,
        array $similarityParameters,
        ?array $customSimilarityParameters,
    ): array {
        $track = $baseTrack;
        $parameters = $similarityParameters;

        $customTrackParams = $customSimilarityParameters['track'] ?? null;

        if ($customTrackParams) {
            if (isset($customTrackParams['year'])) {
                $track = $track->withYear($customTrackParams['year']);
            }

            if (isset($customTrackParams['genre'])) {
                $track = $track->withGenre($customTrackParams['genre']);
            }

            if (isset($customTrackParams['bpm'])) {
                $track = $track->withBpm($customTrackParams['bpm']);
            }

            if (isset($customTrackParams['initial_key'])) {
                $track = $track->withInitialKey($customTrackParams['initial_key']);
            }
        }

        $customProviders = $customSimilarityParameters['providers']['names'] ?? null;

        if ($customProviders) {
            // TODO: Do poprawienia: Dane formularza, takie jak nazwy powinny pochodzić z PHP-a,
            //       co rozwiązuje problem mapowania nazw, wygody i zdublowanego kodu (np. nazw providerów).

            $nameToClassname = [
                'musly' => Musly::class,
                'bpm' => Bpm::class,
                'year' => Year::class,
                'genre' => Genre::class,
                'musicalKey' => MusicalKey::class,
            ];

            $enabledProviders = array_filter(
                $nameToClassname,
                static fn($providerName) => in_array($providerName, $customSimilarityParameters['providers']['names'], true),
                ARRAY_FILTER_USE_KEY
            );

            $parameters['providers']['enabled'] = array_values($enabledProviders);
        }

        // @todo: docelowo powinno być wstrzykiwane poprzez DI (zob. komentarz w container.inc przy Similarity)
        $similarity = new Similarity($this->trackRepository, $parameters);

        return $similarity->getSimilarTracks($track);
    }

    /**
     * Zwraca klucze podobne do klucza podanego utworu
     *
     * @param Track $track
     * @return array|null
     */
    private function getTrackKeyInfo(Track $track): ?array
    {
        $musicalKeyInfo = MusicalKeyInfo::factory(); // faktorka i konstruktor do przemyślenia

        return $musicalKeyInfo->get($track->getInitialKey());
    }
}
