<?php

namespace Assistant\Module\Track\Extension;

use KeyTools\KeyTools;
use SplFileInfo;
use Stringy\StaticStringy as S;

/**
 * Wrzucone na szybko, bez analizy. Pewnie powinno być bliżej modułu sugestii i jakoś w niego wpięte.
 * Docelowo byłoby spoko próbować zwracać kilka sugestii, w tym generowanych na podstawie metadanych
 *
 * @see TrackMetadataSuggestionsBuilder
 */
final class TrackFilenameSuggestion
{
    public function getSuggestedFilename(SplFileInfo $file): string
    {
        $extension = sprintf('.%s', $file->getExtension());
        $filename = $file->getBasename($extension);

        $suggested = $filename;

        // zamień _-_ na -
        $suggested = str_replace([ '_-_' ], ' - ', $suggested);

        // usuń suffix
        $suggested = preg_replace('/-\w{2,3}$/i', '', $suggested);

        // usuń -
        $suggested = preg_replace('/(\w)-(\w)/i', '$1 $2', $suggested);

        // usuń adresy
        $suggested = preg_replace('/\[(?:www\.)?([a-z0-9\-]+)\.[a-z.]+\/?\s*]$/i', ' ', $suggested);
        $suggested = preg_replace('/(?:www\.)?([a-z0-9\-]+)\.[a-z.]+\/?\s*$/i', ' ', $suggested);

        // usuń --
        $suggested = preg_replace('/-{2,}/', ' ', $suggested);

        // usuń klucz

        $addLeadingZero = function (string $key): string {
            $index = (int) rtrim($key, 'ABDM');

            return $index < 10 ? '0' . $key : $key;
        };

        $keys = array_merge(
            KeyTools::NOTATION_KEYS_CAMELOT_KEY,
            array_map($addLeadingZero, KeyTools::NOTATION_KEYS_CAMELOT_KEY),
            KeyTools::NOTATION_KEYS_MUSICAL,
            KeyTools::NOTATION_KEYS_MUSICAL_ALT,
            KeyTools::NOTATION_KEYS_MUSICAL_BEATPORT,
            KeyTools::NOTATION_KEYS_MUSICAL_ESSENTIA,
            KeyTools::NOTATION_KEYS_OPEN_KEY,
            array_map($addLeadingZero, KeyTools::NOTATION_KEYS_OPEN_KEY),
        );

        $keys = array_values(array_unique($keys));
        usort($keys, fn ($a, $b) => strlen($b) - strlen($a)); // najkrótsze na koniec

        $regexp = array_map(fn ($key) => '\b' . $key . '\b', $keys);
        $regexp = '/' . implode('|', $regexp) . '/i';

        $suggested = preg_replace($regexp, ' ', $suggested);

        // usuń _
        $suggested = str_replace('_', ' ', $suggested);

        // usuń wielokrotne spacje
        $suggested = S::collapseWhitespace($suggested);

        // pierwszy trim
        $suggested = S::trim($suggested, ' -.');

        // usuń numer, jeśli jest na początku
        $suggested = preg_replace('/\(?(\d+)[._]?\)?\s?/m', '', $suggested);

        // usuń wszystko w nawisach kwadratowych z końca
        $suggested = preg_replace('/\[[\w _]+]\s*$/i', '', $suggested);

        // usuń wielokrotne - -
        $suggested = preg_replace('/[- -]{2,}/', ' - ', $suggested);

        // drugi trim (po ew. usunięciu numeru i nawiasów kwadratowych)
        $suggested = S::trim($suggested, ' -.');

        // kapitalizuj wyrazy
        $suggested = S::toTitleCase($suggested);

        // dodaj rozszerzenie
        $suggested .= strtolower($extension);

        return $suggested;
    }
}
