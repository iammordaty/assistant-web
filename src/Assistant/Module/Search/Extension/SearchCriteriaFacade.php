<?php

namespace Assistant\Module\Search\Extension;

use Psr\Http\Message\ServerRequestInterface as Request;

final class SearchCriteriaFacade
{
    private const DEFAULTS = [
        'name' => '',
        'artist' => '',
        'title' => '',
        'genre' => '',
        'publisher' => '',
        'year' => '',
        'initial_key' => '',
        'bpm' => '',
        'indexed_date' => '',
    ];

    public static function createFromSearchRequest(Request $request): SearchCriteria
    {
        $queryParams = array_merge(self::DEFAULTS, $request->getQueryParams());

        $name = trim($queryParams['name']) ?: null;
        $artist = trim($queryParams['artist']) ?: null;
        $title = trim($queryParams['title']) ?: null;

        $genres = explode(',', $queryParams['genre']);
        $genres = self::unique($genres) ?: null;

        $publishers = explode(',', $queryParams['publisher']);
        $publishers = self::unique($publishers) ?: null;

        $years = YearMinMaxExpressionParser::parse($queryParams['year']);

        if (!$years) {
            $years = explode(',', $queryParams['year']);
            $years = self::unique($years);
            $years = array_map(fn ($year) => (int) $year, $years);
        }

        $initialKeys = explode(',', $queryParams['initial_key']);
        $initialKeys = self::unique($initialKeys);
        $initialKeys = array_map(fn ($key) => strtoupper($key), $initialKeys);

        $bpm = NumberMinMaxExpressionParser::parse($queryParams['bpm']);

        if (!$bpm) {
            $bpm = explode(',', $queryParams['bpm']);
            $bpm = self::unique($bpm);
            $bpm = array_map(fn ($value) => (float) $value, $bpm);
        }

        $indexedDates = DateTimeMinMaxExpressionParser::parse($queryParams['indexed_date']);

        if (!$indexedDates) {
            $indexedDates = explode(',', $queryParams['indexed_date']);
            $indexedDates = self::unique($indexedDates);
            $indexedDates = array_map(fn ($date) => (int) $date, $indexedDates);
        }

        $searchCriteria = new SearchCriteria(
            $name,
            $artist,
            $title,
            $genres,
            $publishers,
            $years,
            $initialKeys,
            $bpm,
            $indexedDates,
        );

        return $searchCriteria;
    }

    public static function createFromName(string $name): SearchCriteria
    {
        $searchCriteria = new SearchCriteria(name: trim($name));

        return $searchCriteria;
    }

    // do osobnej klasy / funkcji. wyszukać w projekcie podobne wywołania
    private static function unique(?array $values): array
    {
        $values = array_map('trim', $values ?: []);

        return array_values(array_unique(array_filter($values)));
    }
}
