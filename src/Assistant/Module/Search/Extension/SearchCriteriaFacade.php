<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Repository\Regex;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SearchCriteriaFacade
{
    private const DEFAULTS = [
        'artist' => null,
        'bpm' => null,
        'genre' => null,
        'guid' => null,
        'indexed_date' => null,
        'initial_key' => null,
        'name' => null,
        'publisher' => null,
        'title' => null,
        'year' => null,
    ];

    public static function createFromSearchRequest(Request $request): SearchCriteria
    {
        $queryParams = array_merge(self::DEFAULTS, $request->getQueryParams());

        $name = $queryParams['name'] ? Regex::contains(trim($queryParams['name'])) : null;
        $guid = $queryParams['guid'] ? Regex::exact(trim($queryParams['guid'])) : null;
        $artist = $queryParams['artist'] ? Regex::contains(trim($queryParams['artist'])) : null;
        $title = $queryParams['title'] ? Regex::contains(trim($queryParams['title'])) : null;

        $genres = explode(',', trim($queryParams['genre']));
        $genres = self::unique($genres);
        $genres = array_map(fn ($genre) => Regex::exact($genre), $genres) ?: null;

        $publishers = explode(',', trim($queryParams['publisher']));
        $publishers = self::unique($publishers);
        $publishers = array_map(fn ($publisher) => Regex::exact($publisher), $publishers) ?: null;

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
            $guid,
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
        $regex = Regex::contains($name);
        $searchCriteria = new SearchCriteria(name: $regex);

        return $searchCriteria;
    }

    public static function createFromPathname(string $pathname): SearchCriteria
    {
        $searchCriteria = new SearchCriteria(pathname: $pathname);

        return $searchCriteria;
    }

    // do osobnej klasy / funkcji. wyszukać w projekcie podobne wywołania
    private static function unique(?array $values): array
    {
        $values = array_map('trim', $values ?: []);

        return array_values(array_unique(array_filter($values)));
    }
}
