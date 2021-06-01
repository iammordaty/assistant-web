<?php

namespace Assistant\Module\Track\Controller;

use Assistant\Module\Common;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Track\Extension\Similarity;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\CamelotKeyCode;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use KeyTools\KeyTools;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class TrackController
{
    public function __construct(
        private Config $config,
        private PathBreadcrumbs $pathBreadcrumbs,
        private TrackRepository $trackRepository,
        private Twig $view,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackRepository->getOneByGuid($guid);

        if (!$track) {
            $routeName = 'search.simple.index';
            $data = [];
            $queryParams = [ 'query' => str_replace('-', ' ', $guid) ];

            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $redirectUrl = $routeParser->urlFor($routeName, $data, $queryParams);

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
                'camelotKeyCode' => CamelotKeyCode::class,
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
     * @todo Przenieść do zewnętrznej klasy powiązanej z widokiem
     *
     * @param Track $track
     * @return array|null
     */
    private function getTrackKeyInfo(Track $track): ?array
    {
        $keyTools = KeyTools::fromNotation(KeyTools::NOTATION_CAMELOT_KEY);

        $initialKey = $track->getInitialKey();

        if (!$keyTools->isValidKey($initialKey)) {
            return null;
        }

        $implode = static fn($lines) => implode('<br  />', $lines);

        return [
            'relativeMinorToMajor' => [
                'title' => sprintf('To %s', $keyTools->isMinorKey($initialKey) ? 'major' : 'minor'),
                'value' => $keyTools->relativeMinorToMajor($initialKey),
                'description' => $implode([
                    'This combination will likely sound good because the notes of both scales are the same,',
                    'but the root note is different. The energy of the room will change dramatically.',
                ]),
            ],
            'perfectFourth' => [
                'title' => 'Perfect fourth',
                'value' => $keyTools->perfectFourth($initialKey),
                'description' => $implode([
                    'I like to say this type of mix will take the crowd deeper.',
                    'It won\'t raise the energy necessarily but will give your listeners goosebumps!',
                ]),
            ],
            'perfectFifth' => [
                'title' => 'Perfect fifth',
                'value' => $keyTools->perfectFifth($initialKey),
                'description' => 'This will raise the energy in the room.',
            ],
            'minorThird' => [
                'title' => 'Minor third',
                'value' => $keyTools->minorThird($initialKey),
                'description' => $implode([
                    'While these scales have 3 notes that are different,',
                    'I\'ve found that they still sound good played together',
                    'and tend to raise the energy of a room.',
                ]),
            ],
            'halfStep' => [
                'title' => 'Half step',
                'value' => $keyTools->halfStep($initialKey),
                'description' => $implode([
                    'While these two scales have almost no notes in common,',
                    'musically they shouldn’t sound good together but I\'ve found if you plan it right',
                    'and mix a percussive outro of one song with a percussive intro of another song,',
                    'and slowly bring in the melody this can have an amazing effect musically and',
                    'raise the energy of the room dramatically.',
                ]),
            ],
            'wholeStep' => [
                'title' => 'Whole step',
                'value' => $keyTools->wholeStep($initialKey),
                'description' => $implode([
                    'This will raise the energy of the room. I like to call it "hands in the air" mixing,',
                    'and others might call it "Energy Boost mixing".',
                ])
            ],
            'dominantRelative' => [
                'title' => 'Dominant relative',
                'value' => $keyTools->dominantRelative($initialKey),
                'description' => $implode([
                    'I\'ve found this is the best way to go from Major to Minor keys',
                    'and from Minor to Major because the scales only have one note difference',
                    'and the combination sounds great.',
                ])
            ],
        ];
    }
}
