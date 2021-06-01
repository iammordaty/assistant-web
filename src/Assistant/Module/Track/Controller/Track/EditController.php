<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Track\Extension\TrackService;
use Cocur\BackgroundProcess\BackgroundProcess;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class EditController
{
    public function __construct(
        private Id3Adapter $id3Adapter,
        private TrackService $trackService,
        private Twig $view,
    ) {
    }

    /**
     * @todo Dodać pole wyszukiwania, z którego beatportowa będzie próbowała pobrać dane (artist - title, url, id)
     * @todo Wyciągnąć z kontrolera i przerzucić do innej klasy
     * @todo Część klas (m.in. BeatportApiClient) przerzucić do containera dla wygodniejszej inicjalizacji
     */
    public function edit(Request $request, Response $response): Response
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->createFromFile($pathname);

        if (!$track) {
            // to może być oprogramowane jako middleware. uwaga - poniżej jest to samo
            // https://stackoverflow.com/questions/57648078/replacement-for-notfoundhandler-setting/57648863
            // https://www.slimframework.com/docs/v4/middleware/error-handling.html

            if ($this->trackService->getLocationArbiter()->isInCollection($pathname)) {
                $routeName = 'search.simple.index';
                $data = [];
                $queryParams = [ 'query' => str_replace('-', ' ', $pathname) ];
            } else {
                $routeName = 'directory.browse.incoming';
                $data = [];
                $queryParams = [ ];
            }

            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $redirectUrl = $routeParser->urlFor($routeName, $data, $queryParams);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(404);

            return $redirect;
        }

        $query = $request->getQueryParams()['query'] ?? null;

        if (!$query) {
            $query = pathinfo($track->getPathname(), PATHINFO_FILENAME);
        }

        $suggestions = $this->getMetadataSuggestions($query);

        return $this->view->render($response, '@track/edit/edit.twig', [
            'suggestions' => $suggestions,
            'menu' => 'track',
            'metadata' => [
                'fields' => self::getEditableMetadataFields(),
                'options' => self::getMetadataOptions(),
            ],
            'pathname' => $pathname,
            'query' => $query,
            'track' => $track,
        ]);
    }

    /**
     * @todo Na tyle ile pozwala biblioteka, opcja usuwania innych tagów powinna usuwać zdjęcie, tag id3v1, lyrics,
     *       ape (oraz inne) oraz niewspierane pola z id3v2
     */
    public function save(Request $request, Response $response): Response
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->createFromFile($pathname);

        if (!$track) {
            if ($this->trackService->getLocationArbiter()->isInCollection($pathname)) {
                $routeName = 'search.simple.index';
                $data = [];
                $queryParams = [ 'query' => str_replace('-', ' ', $pathname) ];
            } else {
                $routeName = 'directory.browse.incoming';
                $data = [];
                $queryParams = [ ];
            }

            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $redirectUrl = $routeParser->urlFor($routeName, $data, $queryParams);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(404);

            return $redirect;
        }

        $postData = $request->getParsedBody();

        // @todo, to powinno dać się ustawić po stronie Adaptera jako osobne flagi, bez konieczności
        //        nadpisywania całej tablicy (dot. setId3WriterOptions)

        $this->id3Adapter
            ->setFile($track->getFile())
            ->setId3WriterOptions([
                'tag_encoding' => 'UTF-8',
                'tagformats' => [ 'id3v2.3' ],
                'remove_other_tags' => isset($postData['remove-other-tags']),
            ]);

        $metadata = [
            'artist' => $postData['artist'],
            'title' => $postData['title'],
            'album' => $postData['album'],
            'track_number' => $postData['track_number'],
            'publisher' => $postData['publisher'],
            'genre' => $postData['genre'],
            'year' => $postData['year'],
            'initial_key' => $postData['initial_key'],
            'bpm' => $postData['bpm'],
        ];

        // mało eleganckie, ogarnąć zwykłymi if-ami
        foreach ($metadata as $name => $value) {
            if (empty($value)) {
                unset($metadata[$name]);
            }
        }

        // zapobiega usunięciu danych w przypadku braku ich podania
        if (empty($metadata['initial_key']) && $track->getInitialKey()) {
            $metadata['initial_key'] = $track->getInitialKey();
        }

        if (empty($metadata['bpm']) && $track->getBpm()) {
            $metadata['bpm'] = $track->getBpm();
        }

        // @todo: try...catch i wyświetlenie ew. wyjątku na froncie
        $this->id3Adapter->writeId3v2Metadata($metadata, isset($postData['remove-other-tags']));

        if (isset($postData['task:calculate-audio-data'])) {
            $command = sprintf(
                'php /data/app/console.php track:calculate-audio-data -w "%s"',
                $track->getFile()->getPathname()
            );

            (new BackgroundProcess($command))->run();
        }

        /** @noinspection PhpIfWithCommonPartsInspection, celowe to jest do wyniesienia później */
        if ($this->trackService->getLocationArbiter()->isInCollection($pathname)) {
            $routeName = 'track.track.index';
            $data = [ 'guid' => $track->getGuid() ];
            $queryParams = [ ];
        } else {
            $routeName = 'track.edit.edit';
            $data = [ 'pathname' => $track->getFile()->getPathname() ];
            $queryParams = [ ];
        }

        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $redirectUrl = $routeParser->urlFor($routeName, $data, $queryParams);

        $redirect = $response
            ->withHeader('Location', $redirectUrl)
            ->withStatus(302);

        return $redirect;
    }

    /**
     * @fixme Przy problemach z połączeniem wywala się cała aplikacja, poprawić.
     * @todo Dodać obsługę generowanie sugestii z metadanych pliku oraz nazwy pliku
     *
     * @todo Dodać obsługę beatport api v4 po uzyskaniu dostępu.
     */
    private function getMetadataSuggestions(string $query): array
    {
        // roboczo. może powinno zostać rozdzielone na więcej klas
        // - budującą obiekty klas, które wyszukują kawałki
        // - klasę, która wyszukuje kawałki
        // - klasę odpowiedzialną za budowanie sugestii na podstawie znalezionych utworów
        //
        // ale jeszcze do zastanowienia się na spokojnie

        $trackMetadataSuggestions = TrackMetadataSuggestions::factory();

        return $trackMetadataSuggestions->get($query);
    }

    // to w sumie mogłoby być w modelu Track
    private static function getEditableMetadataFields(): array
    {
        return [
            [ 'field' => 'artist', 'title' => 'Wykonawca' ],
            [ 'field' => 'title', 'title' => 'Tytuł utworu' ],
            [ 'field' => 'album', 'title' => 'Album' ],
            [ 'field' => 'track_number', 'title' => 'Nr ścieżki' ],
            [ 'field' => 'publisher', 'title' => 'Wydawca' ],
            [ 'field' => 'genre', 'title' => 'Gatunek' ],
            [ 'field' => 'year', 'title' => 'Rok' ],
            [ 'field' => 'initial_key', 'title' => 'Tonacja' ],
            [ 'field' => 'bpm', 'title' => 'BPM' ],
        ];
    }

    /**
     * @todo Przenieść do innej klasy, chyba tej samej co getMetadataSuggestions, bo związanej z widokiem
     *
     * @return string[][]
     */
    private static function getMetadataOptions(): array
    {
        return [
            [ 'option' => 'remove-other-tags', 'title' => 'Usuń pozostałe metadane zapisane w pliku' ],
            [ 'option' => 'task:calculate-audio-data', 'title' => 'Oblicz tonację i BPM utworu' ],
        ];
    }
}
