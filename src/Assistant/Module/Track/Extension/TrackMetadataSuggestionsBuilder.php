<?php

// Wrzucone na szybko, być może powinno leżeć bliżej modelu
namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Extension\Beatport\BeatportChart;
use Assistant\Module\Common\Extension\Beatport\BeatportTrack;
use Assistant\Module\Track\Model\TrackMetadataSuggestions;
use Stringy\StaticStringy as S;

/**
 * Klasa zajmująca się budowaniem sugestii (propozycji) pól metadanych dla pliku muzycznego
 *
 * @TODO Dodać sugestie tagów pobieranych przez Essentię
 */
final class TrackMetadataSuggestionsBuilder
{
    private BackendClient $backendClient;

    public function __construct(BackendClient $backendClient)
    {
        $this->backendClient = $backendClient;
    }

    public function fromBeatportTrack(BeatportTrack $beatportTrack): TrackMetadataSuggestions
    {
        // Być może w przyszłości to powinno zostać oddelegowane do wyspecjalizowanej dla Beatport klasy,
        // ponieważ inne źródła będą generować title na podstawie innego zestawu danych (pól, rodzaju odpowiedzi)

        // Na pewno, bo - patrząc na ogół - beatport (albo inne źródło) może nie zwrócić wyników
        // albo zwrócić wynik nietrafiony.
        // W takich sytuacjach warto mieć fallback na metadane zapisane w pliku i standardowe zmiany
        // takie, jak remiks w nawiasach kwadratowych feat z małych, feat. zamiast ft., i tak dalej
        // Poza tym, trzeba wykonać standardowe operacje, chociażby takie jak feat. w polu "artists",
        // nazwa remiksu w klamrach albo wyciąć zero z numeru kawałka

        // TODO: Zastanowić się jak ogarnąć powyższe.

        $artist = $this->getArtist($beatportTrack->getArtists(), $beatportTrack->getTitle());

        $title = $this->getTitle(
            $beatportTrack->getTitle(),
            $beatportTrack->getMixName(),
            $beatportTrack->getRemixers(),
        );

        $album = $this->getAlbum($beatportTrack->getRelease()->getName(), $beatportTrack->getRemixers());
        $trackNumber = $this->getTrackNumber($beatportTrack->getTrackNumber());
        $year = $this->getYear($beatportTrack->getReleaseDate());
        $genre = $this->getGenre($beatportTrack->getGenre(), $beatportTrack->getSubGenre());
        $publisher = $this->getPublisher($beatportTrack->getRelease()->getLabel());
        $bpm = $this->getBpm();
        $initialKey = $this->getInitialKey();

        $tags = $this->getTags(
            $beatportTrack->getArtists(), // artyści wyciągnięci z title jako feat
            $beatportTrack->getRemixers(),
            $beatportTrack->getGenre(),
            $beatportTrack->getSubGenre(),
            $beatportTrack->getCharts(),
        );

        $metadataSuggestions = new TrackMetadataSuggestions(
            $artist,
            $title,
            $album,
            $trackNumber,
            $year,
            $genre,
            $publisher,
            $bpm,
            $initialKey,
            $tags,
        );

        return $metadataSuggestions;
    }

    private function getArtist(array $artists, string $title): array
    {
        // <editor-fold desc="Info">
        // https://www.beatport.com/track/drift-feat-aparde-eelke-kleijn-remix/9332127
        // 1. wygląda na to, że feat jest w tablicy "artists"

        // https://www.beatport.com/release/messiah-feat-h-los/2785481
        // 1. wygląda na to, że feat jest w tablicy "artists"
        // 2. feat. może występować też w nazwie albumu, dodać sugestię która to wycina

        // https://www.beatport.com/track/this-game-feat-bertie-blackman-eleven-remix/7030735
        // 1. tutaj artysta "Bertie Blackman" zapisany jest jako ciąg w title, nie ma go w tablicy artists
        // </editor-fold>

        // Jeśli feat jest w title to faworyzować wielkość znaków z title'a
        // Jeśli feat istnieje w tablicy $artists, to ją stamtąd usuwać

        $result = preg_match('/[\s(](?:feat|ft?)\.?\s([\w, &\.]+)/ui', $title, $matches);

        $featuredArtist = $result !== 0
            ? S::collapseWhitespace($matches[1])
            : null;

        $artists = array_filter(
            $artists,
            static fn($artist) => S::toLowerCase($artist) !== S::toLowerCase($featuredArtist)
        );

        $artistsCount = count($artists);

        $suggestions = [];

        if ($artistsCount === 1) {
            $suggestions = $artists;
        }

        if ($artistsCount === 2) {
            [ $artistOne, $artistTwo ] = $artists;

            $suggestions[] = implode(', ', [ $artistOne, $artistTwo ]);
            $suggestions[] = implode(' & ', [ $artistOne, $artistTwo ]);
            $suggestions[] = implode(', ', [ $artistTwo, $artistOne ]);
            $suggestions[] = implode(' & ', [ $artistTwo, $artistOne ]);
        }

        // to jest dość rzadka sytuacja, więc na ten moment upraszczamy
        if ($artistsCount === 3) {
            $suggestions[] = implode(', ', $artists);
            $suggestions[] = implode(' & ', $artists);
        }

        if ($featuredArtist) {
            $suggestions = array_map(
                static fn($suggestion) => sprintf('%s feat. %s', $suggestion, $featuredArtist),
                $suggestions
            );
        }

        $suggestions = self::getUniqueSortedSuggestions($suggestions);

        return $suggestions;
    }

    private function getTitle(string $name, string $mixName, ?array $remixers): array
    {
        $removeFeat = static function (string $title): string {
            $regex = '/(?:\s|\()(?:feat|ft?)\.?\s([\w, &\.]+)\)?/iu';

            return S::collapseWhitespace(preg_replace($regex, ' ', $title));
        };

        $nameTitleCase = $removeFeat(S::toTitleCase($name));

        $suggestions = [
            str_replace(['(', ')'], ['[', ']'], $removeFeat(S::toTitleCase($name))),
            sprintf('%s [%s]', $nameTitleCase, $mixName),
        ];

        if ($remixers) {
            $suggestions[] = sprintf('%s [%s Remix]', $nameTitleCase, implode(', ', $remixers));
        }

        $suggestions = self::getUniqueSortedSuggestions($suggestions);

        return $suggestions;
    }

    private function getAlbum(string $releaseName, ?array $remixers): array
    {
        $removeFeat = static function (string $title): string {
            $regex = '/(?:\s|\()(?:feat|ft?)\.?\s([\w, &\.]+)\)?/iu';

            return S::collapseWhitespace(preg_replace($regex, ' ', $title));
        };

        $titleCase = $removeFeat(S::toTitleCase($releaseName));

        $suggestions = [
            $removeFeat($releaseName),
            $titleCase,
        ];

        $bracketPos = strpos($titleCase, '(');

        $releaseNameWithoutBrackets = $bracketPos === false
            ? $titleCase
            : trim(S::substr($titleCase, 0, $bracketPos));

        if ($bracketPos !== false) {
            $suggestions[] = $releaseNameWithoutBrackets;
        }

        if ($remixers) {
            $suggestions[] = sprintf('%s (%s Remix)', $titleCase, implode(', ', $remixers));
            $suggestions[] = sprintf('%s (%s Remix)', $releaseNameWithoutBrackets, implode(', ', $remixers));

            // dla świętego spokoju tutaj przydałaby się jeszcze jedna sugestia, zamieniająca ") (" na " / "
            // wynikająca z takiego przykładu.
            // In The Air Tonight (Club Remixes) (Passenger 10 Remix)
            // to samo dotyczy tytułu utworu
        }

        // Spróbować wyeliminować taki przypadek
        // I Still Keep Love For You (Einmusik Remix)
        // I Still Keep Love For You (Einmusik Remix) (Einmusik Remix)

        $suggestions = self::getUniqueSortedSuggestions($suggestions);

        return $suggestions;
    }

    private function getTrackNumber(?int $trackNumber): array
    {
        $suggestions = [ ];

        if ($trackNumber) {
            $suggestions[] = $trackNumber;
        }

        return $suggestions;
    }

    private function getYear(\DateTime $releaseDate): array
    {
        $suggestions = [ (int) $releaseDate->format('Y') ];

        return $suggestions;
    }

    private function getGenre(string $genre, ?string $subGenre): array
    {
        $beatportGenres = [ $genre ];

        if ($subGenre) {
            $beatportGenres[] = $subGenre;
        }

        /** @noinspection SpellCheckingInspection */
        $collectionGenres = [
            'Deep House',
            'Electro House',
            'Electronic',
            'Hard Techno',
            'House',
            'Indie Dance',
            'Progressive House',
            'Progressive Trance',
            'Tech House',
            'Techno',
            'Trance',
        ];

        /** @noinspection SpellCheckingInspection */
        $beatportGenreToCollectionGenreMap = [
            'Afro House' => 'House',
            'Dance / Electro Pop' => 'House',
            'Electronica' => 'Electronic',
            'Funky / Groove / Jackin\' House' => 'House',
            'Leftfield House & Techno' => 'House',
            'Melodic House & Techno' => 'Progressive House',
            'Minimal / Deep Tech' => 'Deep House',
            'Nu Disco / Disco' => 'Indie Dance', // :/
            'Progressive' => 'Progressive House',
            'Techno (Peak Time / Driving)' => 'Techno',
            'Techno (Raw / Deep / Hypnotic)' => 'Techno',
        ];

        $beatportGenres = self::getUniqueSortedSuggestions($beatportGenres);

        $suggestions = array_map(
            static fn($beatportGenre) => $beatportGenreToCollectionGenreMap[$beatportGenre] ?? $beatportGenre,
            $beatportGenres
        );

        array_push($suggestions, ...$collectionGenres);

        $suggestions = self::getUniqueSuggestions($suggestions);

        return $suggestions;
    }

    private function getPublisher(string $label): array
    {
        $titleCase = S::toTitleCase($label);

        $suggestions = [ $label, $titleCase ];

        if (($bracketPos = strpos($label, '(')) !== false) {
            $suggestions[] = trim(S::substr($titleCase, 0, $bracketPos));
        }

        $suggestions = self::getUniqueSortedSuggestions($suggestions);

        return $suggestions;
    }

    private function getBpm(): array
    {
        // sugestie obejmują tylko BPM obliczone przez backend

        $suggestions = [ ];

        return $suggestions;
    }

    private function getInitialKey(): array
    {
        // sugestie obejmują tylko klucz obliczony przez backend

        $suggestions = [ ];

        return $suggestions;
    }

    /**
     * @param string[] $artists
     * @param string[]|null $remixers
     * @param string $genre
     * @param string|null $subGenre
     * @param BeatportChart[]|null $charts
     * @return string[]
     */
    private function getTags(array $artists, ?array $remixers, string $genre, ?string $subGenre, ?array $charts): array
    {
        // TODO: To powinno zwracać tablicę obiektów Tag, która może być stringiem (json_encoded) i na wszelki wypadek
        //       powinna zawierać informację o tym czym jest (remixer, artist, genre, itd), a także oddzielenie
        //       nazwy od sluga / guida

        $suggestions = array_merge($artists);

        if ($genre) {
            $suggestions[] = $genre;
        }
        if ($subGenre) {
            $suggestions[] = $subGenre;
        }

        if ($remixers) {
            array_push($suggestions, ...$remixers);
        }

        if ($charts) {
            $officialCharts = array_filter($charts, fn(BeatportChart $chart) => $chart->isOfficial());

            foreach ($officialCharts as $chart) {
                $suggestions[] = $chart->getArtist();

                array_push($suggestions, ...$chart->getGenres());
            }
        }

        // plus, w przyszłości, tagi wygenerowane przez Essentię

        $suggestions = self::getUniqueSortedSuggestions($suggestions);

        return $suggestions;
    }

    // rozdzielić. niech to będzie unique(s) i sorted(s), czyli: unique(sorted($array));
    private static function getUniqueSortedSuggestions(array $suggestions): array
    {
        $unique = self::getUniqueSuggestions($suggestions);

        natcasesort($unique);

        return array_values($unique);
    }

    private static function getUniqueSuggestions(array $suggestions): array
    {
        $result = array_unique($suggestions);

        return array_values($result);
    }
}
