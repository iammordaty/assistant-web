<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\TrackService;
use Cocur\BackgroundProcess\BackgroundProcess;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;
use Slim\Views\Twig;

final class EditController
{
    public function __construct(
        private Id3Adapter $id3Adapter,
        private RouteResolver $routeResolver,
        private TrackService $trackService,
        private Twig $view,
    ) {
    }

    /**
     * @todo Dodać pole wyszukiwania, z którego beatportowa będzie próbowała pobrać dane (artist - title, url, id)
     * @todo Wyciągnąć z kontrolera i przerzucić do innej klasy
     * @todo Część klas (m.in. BeatportApiClient) przerzucić do containera dla wygodniejszej inicjalizacji
     */
    public function edit(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->createFromFile($pathname);

        if (!$track) {
            return $this->getNotFoundRedirect($response, $pathname);
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
    public function save(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->createFromFile($pathname);

        if (!$track) {
            return $this->getNotFoundRedirect($response, $pathname);
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
            'track_number' => $postData['trackNumber'],
            'publisher' => $postData['publisher'],
            'genre' => $postData['genre'],
            'year' => $postData['year'],
            'initial_key' => $postData['initialKey'],
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
                'php /data/bin/console.php track:calculate-audio-data -w "%s"',
                $track->getFile()->getPathname()
            );

            (new BackgroundProcess($command))->run();
        }

        if ($this->trackService->getLocationArbiter()->isInCollection($pathname)) {
            $routeName = 'track.track.index';
            $params = [ 'guid' => $track->getGuid() ];
        } else {
            $routeName = 'track.edit.edit';
            $params = [ 'pathname' => $track->getFile()->getPathname() ];
        }

        $route = Route::create($routeName)->withParams($params);

        $redirect = $response
            ->withRedirect($this->routeResolver->resolve($route))
            ->withStatus(StatusCodeInterface::STATUS_FOUND);

        return $redirect;
    }

    /**
     * @fixme Przy problemach z połączeniem wywala się cała aplikacja, poprawić.
     * @todo Dodać obsługę generowanie sugestii z metadanych pliku oraz nazwy pliku
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
            [ 'field' => 'trackNumber', 'title' => 'Nr ścieżki' ],
            [ 'field' => 'publisher', 'title' => 'Wydawca' ],
            [ 'field' => 'genre', 'title' => 'Gatunek' ],
            [ 'field' => 'year', 'title' => 'Rok' ],
            [ 'field' => 'initialKey', 'title' => 'Tonacja' ],
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

    private function getNotFoundRedirect(Response $response, string $pathname): ResponseInterface
    {
        if ($this->trackService->getLocationArbiter()->isInCollection($pathname)) {
            $routeName = 'search.simple.index';
            $query = [ 'query' => str_replace('-', ' ', $pathname) ];
        } else {
            $routeName = 'directory.browse.incoming';
            $query = [];
        }

        $route = Route::create($routeName)->withQuery($query);

        $redirect = $response
            ->withRedirect($this->routeResolver->resolve($route))
            ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

        return $redirect;
    }
}
