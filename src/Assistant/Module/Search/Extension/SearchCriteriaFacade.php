<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Storage\Regex;

final class SearchCriteriaFacade
{
    private const DEFAULTS = [
        'artist' => '',
        'bpm' => '',
        'genre' => '',
        'guid' => '',
        'indexed_date' => '',
        'initial_key' => '',
        'name' => '',
        'publisher' => '',
        'title' => '',
        'year' => '',
    ];

    public static function createFromFields(array $fields): SearchCriteria
    {
        $fields = array_merge(self::DEFAULTS, $fields);

        $name = $fields['name'] ?: null;
        $guid = $fields['guid'] ? Regex::exact(trim($fields['guid'])) : null;
        $artist = $fields['artist'] ? Regex::contains(trim($fields['artist'])) : null;
        $title = $fields['title'] ? Regex::contains(trim($fields['title'])) : null;

        $genres = explode(',', trim($fields['genre']));
        $genres = self::unique($genres);
        $genres = array_map(fn ($genre) => Regex::exact($genre), $genres) ?: null;

        $publishers = explode(',', trim($fields['publisher']));
        $publishers = self::unique($publishers);
        $publishers = array_map(fn ($publisher) => Regex::startsWith($publisher), $publishers) ?: null;

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

        $indexedDates = DateTimeMinMaxExpressionParser::parse($fields['indexed_date']);

        if (!$indexedDates) {
            $indexedDates = explode(',', $fields['indexed_date']);
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
        $searchCriteria = new SearchCriteria(name: $name);

        return $searchCriteria;
    }

    public static function createFromGuid(Regex|string $guid): SearchCriteria
    {
        $searchCriteria = new SearchCriteria(guid: $guid);

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
