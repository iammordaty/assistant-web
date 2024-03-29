<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Storage\Regex;
use Assistant\Module\Track\Extension\Similarity\Similarity;
use DateTime;

final class SearchCriteriaFacade
{
    public const DEFAULTS = [
        'artist' => '',
        'bpm' => '',
        'genre' => '',
        'guid' => '',
        'indexed_date' => '',
        'initial_key' => '',
        'is_favorite' => null,
        'name' => '',
        'publisher' => '',
        'title' => '',
        'year' => '',
    ];

    /**
     * @idea Z tej metody należy wyodrębnić explode-y i przenieść do kontrolera lub serwisu (raczej to pierwsze).
     *       Update: raczej do klasy Request, zgodnie z sugestią poniżej.
     *
     * @idea Najlepiej byłoby z niej zrezygnować na rzecz metody przyjmującej wiele SearchCriteria.
     *       Uprości to kod, a także wyeliminuje tablice asocjacyjne na rzecz obiektów.
     *       Rozbijanie przecinków i filtrowanie mogłoby zostać przeniesione do klasy Request, wzorem
     *       SimilarityParametersRequest. Do klasy Request można byłoby także przenieść defaults-y, o ile byłyby
     *       jeszcze potrzebne.
     *
     * @see Similarity::getSimilarityCriteria(); w TrackSearchService też będzie się dało to wykorzystać
     */
    public static function createFromFields(array $fields): SearchCriteria
    {
        $name = $fields['name'] ?: null;
        $guid = $fields['guid'] ? Regex::exact(trim($fields['guid'])) : null;
        $artist = $fields['artist'] ? Regex::contains(trim($fields['artist'])) : null;
        $title = $fields['title'] ? Regex::contains(trim($fields['title'])) : null;

        $genres = explode(',', trim($fields['genre']));
        $genres = self::unique($genres);
        $genres = array_map(fn ($genre) => Regex::exact($genre), $genres) ?: null;

        $publishers = explode(',', trim($fields['publisher']));
        $publishers = self::unique($publishers);
        $publishers = array_map(fn ($publisher) => Regex::containsWordPart($publisher), $publishers) ?: null;

        $years = YearMinMaxExpressionParser::parse($fields['year']);

        if (!$years) {
            $years = explode(',', $fields['year']);
            $years = self::unique($years);
            $years = array_map(fn ($year) => (int) $year, $years);
        }

        $initialKeys = explode(',', $fields['initial_key']);
        $initialKeys = self::unique($initialKeys);
        $initialKeys = array_map(fn ($key) => strtoupper($key), $initialKeys);

        $bpm = NumberMinMaxExpressionParser::parse($fields['bpm']);

        if (!$bpm) {
            $bpm = explode(',', $fields['bpm']);
            $bpm = self::unique($bpm);
            $bpm = array_map(fn ($value) => (float) $value, $bpm);
        }

        $isFavorite = filter_var($fields['is_favorite'], FILTER_VALIDATE_BOOLEAN) ?: null;

        $indexedDates = DateTimeMinMaxExpressionParser::parse($fields['indexed_date']);

        if (!$indexedDates) {
            $indexedDates = explode(',', $fields['indexed_date']);
            $indexedDates = self::unique($indexedDates);
            $indexedDates = array_map(fn ($date) => (int) $date, $indexedDates);
        }

        $pathname = $fields['pathname'] ?? null;

        $searchCriteria = new SearchCriteria(
            $name,
            $guid,
            $artist,
            $title,
            $genres,
            $publishers,
            $years,
            $initialKeys,
            $bpm,
            $isFavorite,
            $indexedDates,
            null,
            $pathname,
        );

        return $searchCriteria;
    }

    public static function createFromName(string $name): SearchCriteria
    {
        $searchCriteria = new SearchCriteria(name: $name);

        return $searchCriteria;
    }

    public static function createFromGuid(Regex|string $guid): SearchCriteria
    {
        $searchCriteria = new SearchCriteria(guid: $guid);

        return $searchCriteria;
    }

    public static function createFromParent(string $parent): SearchCriteria
    {
        $searchCriteria = new SearchCriteria(parent: $parent);

        return $searchCriteria;
    }

    public static function createFromPathname(Regex|string $pathname): SearchCriteria
    {
        $searchCriteria = new SearchCriteria(pathname: [ $pathname ]);

        return $searchCriteria;
    }

    public static function createFromMinIndexedDate(DateTime $indexedDate): SearchCriteria
    {
        $indexedDates = MinMaxInfo::create([ 'gte' => $indexedDate, 'lte' => null ]);
        $searchCriteria = new SearchCriteria(indexedDates: $indexedDates);

        return $searchCriteria;
    }

    // Przenieść osobnej klasy / funkcji. Wyszukać w projekcie podobne wywołania
    private static function unique(?array $values): array
    {
        $values = array_map('trim', $values ?: []);

        return array_values(array_unique(array_filter($values)));
    }
}
