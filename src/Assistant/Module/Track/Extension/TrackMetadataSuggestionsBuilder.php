<?php

namespace Assistant\Module\Track\Extension;

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

        $artist = $this->getArtist($beatportTrack->artists, $beatportTrack->title);

        $title = $this->getTitle(
            $beatportTrack->title,
            $beatportTrack->mixName,
            $beatportTrack->remixers,
        );

        $album = $this->getAlbum($beatportTrack->release->name, $beatportTrack->remixers);
        $trackNumber = $this->getTrackNumber($beatportTrack->trackNumber);
        $year = $this->getYear($beatportTrack->release->date);
        $genre = $this->getGenre($beatportTrack->genre, $beatportTrack->subGenre);
        $publisher = $this->getPublisher($beatportTrack->release->label);
        $bpm = $this->getBpm();
        $initialKey = $this->getInitialKey();

        $tags = $this->getTags(
            $beatportTrack->artists, // artyści wyciągnięci z title jako feat
            $beatportTrack->remixers,
            $beatportTrack->genre,
            $beatportTrack->subGenre,
            $beatportTrack->charts,
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

        // Jeśli feat jest w title to faworyzować wielkość znaków z title-a.
        // Jeśli feat istnieje w tablicy $artists, to ją stamtąd usuwać

        $result = preg_match('/[\s(](?:feat|ft?)\.?\s([\w, &\.]+)/ui', $title, $matches);

        $featuredArtist = $result !== 0
            ? S::collapseWhitespace($matches[1])
            : null;

        $artists = array_values(array_filter(
            $artists,
            static fn ($artist) => S::toLowerCase($artist) !== S::toLowerCase($featuredArtist)
        ));

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
                static fn ($suggestion) => sprintf('%s feat. %s', $suggestion, $featuredArtist),
                $suggestions
            );
        }

        $suggestions = self::sorted(self::unique($suggestions));

        return $suggestions;
    }

    private function getTitle(string $name, string $mixName, ?array $remixers): array
    {
            $name = str_replace(
                [ ' an ', ' at ', ' of ', ' in ', ' the ' ],
                [ ' An ', ' At ', ' Of ', ' In ', ' The ' ],
                $name
            );

        $nameTitleCase = self::removeFeat(S::toTitleCase($name));

        $suggestions = [
            str_replace([ '(', ')' ], [ '[', ']' ], $nameTitleCase),
            sprintf('%s [%s]', $nameTitleCase, $mixName),
        ];

        if ($remixers) {
            $suggestions[] = sprintf('%s [%s Remix]', $nameTitleCase, implode(', ', $remixers));

            foreach ($suggestions as $suggestion) {
                if (str_contains($suggestion, ') (')) {
                    $suggestions[] = str_replace(') (', ' / ', $suggestion);
                }

                if (str_contains($suggestion, '] [')) {
                    $suggestions[] = str_replace('] [', ' / ', $suggestion);
                }
            }
        }

        $suggestions = self::sorted(self::unique($suggestions));

        return $suggestions;
    }

    private function getAlbum(string $releaseName, ?array $remixers): array
    {
        $releaseName = str_replace(
            [ ' an ', ' at ', ' of ', ' in ', ' the ' ],
            [ ' An ', ' At ', ' Of ', ' In ', ' The ' ],
            $releaseName
        );

        $titleCase = self::removeFeat(S::toTitleCase($releaseName));

        $suggestions = [
            self::removeFeat($releaseName),
            $titleCase,
        ];

        $bracketPos = strpos($titleCase, '(');
        $releaseNameWithoutBrackets = null;

        if ($bracketPos !== false) {
            if ($bracketPos === 0) {
                // dla np. Peggy Gou - (It Goes Like) Nanana - Extended Mix usuwamy tylko (It Goes Like), reszta zostaje
                $bracketPos = strpos($titleCase, ')') + 1;

                $releaseNameWithoutBrackets = trim(S::substr($titleCase, $bracketPos));
            } else {
                $releaseNameWithoutBrackets = trim(S::substr($titleCase, 0, $bracketPos));
            }

            $suggestions[] = $releaseNameWithoutBrackets;
        }

        if ($remixers) {
            $remixName = implode(', ', $remixers) . ' Remix';

            if (!str_contains($titleCase, $remixName)) {
                $suggestions[] = sprintf('%s (%s)', $titleCase, $remixName);
            }

            if ($releaseNameWithoutBrackets) {
                $suggestions[] = sprintf('%s (%s Remix)', $releaseNameWithoutBrackets, implode(', ', $remixers));
            }

            foreach ($suggestions as $suggestion) {
                if (str_contains($suggestion, ') (')) {
                    $suggestions[] = str_replace(') (', ' / ', $suggestion);
                }
            }
        }

        $suggestions = self::sorted(self::unique($suggestions));

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

    private function getYear(string $releaseDate): array
    {
        $suggestions = [ (int) (new \DateTime($releaseDate))->format('Y') ];

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
            'Organic House / Downtempo' => 'House',
            'Progressive' => 'Progressive House',
            'Techno (Peak Time / Driving)' => 'Techno',
            'Techno (Raw / Deep / Hypnotic)' => 'Techno',
        ];

        $beatportGenres = self::sorted(self::unique($beatportGenres));

        $suggestions = array_map(
            static fn ($beatportGenre) => $beatportGenreToCollectionGenreMap[$beatportGenre] ?? $beatportGenre,
            $beatportGenres
        );

        array_push($suggestions, ...$collectionGenres);

        $suggestions = self::unique($suggestions);

        return $suggestions;
    }

    private function getPublisher(string $label): array
    {
        $titleCase = S::toTitleCase($label);

        $suggestions = [ $label, $titleCase ];

        if (($bracketPos = strpos($label, '(')) !== false) {
            $suggestions[] = trim(S::substr($titleCase, 0, $bracketPos));
        }

        $suggestions = self::sorted(self::unique($suggestions));

        return $suggestions;
    }

    private function getBpm(): array
    {
        // sugestie obejmują tylko BPM obliczone przez music classifier service

        $suggestions = [ ];

        return $suggestions;
    }

    private function getInitialKey(): array
    {
        // sugestie obejmują tylko klucz obliczony przez music classifier service

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
            $officialCharts = array_filter($charts, fn (BeatportChart $chart) => $chart->isOfficial);

            foreach ($officialCharts as $chart) {
                $suggestions[] = $chart->artist;

                array_push($suggestions, ...$chart->genres);
            }
        }

        // plus, w przyszłości, tagi wygenerowane przez Essentię

        $suggestions = self::sorted(self::unique($suggestions));

        return $suggestions;
    }

    private static function removeFeat(string $string): string
    {
        $regex = '/(?:\s|\()(?:feat|ft?)\.?\s([\w, &\.]+)\)?/iu';

        return S::collapseWhitespace(preg_replace($regex, ' ', $string));
    }

    private static function sorted(array $suggestions): array
    {
        natcasesort($suggestions);

        return array_values($suggestions);
    }

    private static function unique(array $suggestions): array
    {
        $result = array_unique($suggestions);

        return array_values($result);
    }
}
