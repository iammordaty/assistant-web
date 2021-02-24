<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Track\Extension\TrackBuilder;
use Cocur\BackgroundProcess\BackgroundProcess;

class EditController extends AbstractController
{
    /**
     * @todo Dodać pole wyszukiwania, z którego beatportowa będzie próbowała pobrać dane (artist - title, url, id)
     * @todo Wyciągnąć z kontrolera i przerzucić do innej klasy
     * @todo Część klas (m.in. BeatportApiClient) przerzucić do containera dla wygodniejszej inicjalizacji
     *
     * @param $pathname
     */
    public function edit($pathname)
    {
        $track = $this->app->container[TrackBuilder::class]->fromFile($pathname);

        if (!$track) {
            $this->app->redirect(
                // TODO: tylko dla filename, ew. powrót do głównego incoming
                sprintf('%s?query=%s', $this->app->urlFor('search.simple.index'), str_replace(DIRECTORY_SEPARATOR, ' ', $pathname)),
                404
            );
        }

        $query = $this->app->request()->get('query');

        if (!$query) {
            $query = pathinfo($track->getPathname(), PATHINFO_FILENAME);
        }

        $suggestions = $this->getMetadataSuggestions($query);

        $this->app->render('@track/edit/edit.twig', [
            'suggestions' => $suggestions,
            'menu' => 'track',
            'metadata' => [
                'fields' => self::getEditableMetadataFields(),
                'options' => self::getMetadataOptions(),
            ],
            'pathname' => $pathname,
            'query' => $query,
            'track' => $track->toArray(),
        ]);
    }

    /**
     * @param string $pathname
     * @todo Na tyle ile pozwala biblioteka, opcja usuwania innych tagów powinna usuwać zdjęcie, tag id3v1, lyrics,
     *       ape (oraz inne) oraz niewspierane pola z id3v2
     */
    public function save(string $pathname)
    {
        $track = $this->app->container[TrackBuilder::class]->fromFile($pathname);

        if (!$track) {
            $this->app->redirect(
                // TODO: tylko dla filename, ew. powrót do głównego incoming
                sprintf('%s?query=%s', $this->app->urlFor('search.simple.index'), str_replace(DIRECTORY_SEPARATOR, ' ', $pathname)),
                404
            );
        }

        $postData = array_map('trim', $this->app->request->post());

        $removeOtherTags = isset($postData['remove-other-tags']);

        $id3Adapter = new Id3Adapter($track->getFile());

        // @todo, to powinno dać się ustawić po stronie Adaptera jako osobne flagi, bez konieczności
        //        nadpisywania całej tablicy
        $id3Adapter->setId3WriterOptions([
            'tag_encoding' => 'UTF-8',
            'tagformats' => [ 'id3v2.3' ],
            'remove_other_tags' => $removeOtherTags,
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

        $id3Adapter->writeId3v2Metadata($metadata, $removeOtherTags);

        if (isset($postData['task:calculate-audio-data'])) {
            (new BackgroundProcess(
                sprintf('php /data/app/console.php track:calculate-audio-data -w "%s"', $track->getFile()->getPathname())
            ))->run();
        }

        // TODO: tylko dla filename, ew. powrót do głównego incoming
        $this->app->redirect(
             $this->app->urlFor('track.edit.edit', [ 'pathname' => urlencode($track->getFile()->getPathname()) ])
        );
    }

    private function getMetadataSuggestions(string $query): array
    {
        // roboczo. może powinno zostać rozdzielone na więcej klas
        // - budującą obiekty klas, które wyszukują kawałki
        // - klasę, która wyszukuje kawałki
        // - klasę odpowiedzialną za budowanie sugestii na podstawie znalezionych utworów
        //
        // ale jeszcze do zastanowienia się na spokojnie

        $suggestions = TrackMetadataSuggestions::factory($this->app->container);

        return $suggestions->get($query);
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
