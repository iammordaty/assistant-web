<?php

namespace Assistant\Module\Search\Extension\Request;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Search\Extension\Criteria\Regex;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Request\ExpressionParser\DateTimeMinMaxExpressionParser;
use Assistant\Module\Search\Extension\Request\ExpressionParser\ExpressionParser;
use Assistant\Module\Search\Extension\Request\ExpressionParser\NumberMinMaxExpressionParser;
use Assistant\Module\Search\Extension\Request\ExpressionParser\YearMinMaxExpressionParser;

final class SearchRequest
{
    public const array DEFAULTS = [
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

    public function __construct(
        private readonly ?string $name = null,
        private readonly ?string $guid = null,
        private readonly ?string $artist = null,
        private readonly ?string $title = null,
        private readonly ?array $genres = null,
        private readonly ?array $publishers = null,
        private readonly MinMaxInfo|array|null $years = null,
        private readonly ?array $initialKeys = null,
        private readonly MinMaxInfo|array|null $bpm = null,
        private readonly ?bool $isFavorite = null,
        private readonly MinMaxInfo|array|null $indexedDates = null,
        private readonly ?array $pathname = null,
    ) {
    }

    public static function fromQueryParams(array $params): self
    {
        $params = array_merge(self::DEFAULTS, $params);

        return new self(
            name: self::parseString($params['name']),
            guid: self::parseString($params['guid']),
            artist: self::parseString($params['artist']),
            title: self::parseString($params['title']),
            genres: self::parseCommaSeparated($params['genre']),
            publishers: self::parseCommaSeparated($params['publisher']),
            years: self::parseMinMaxOrList($params['year'], YearMinMaxExpressionParser::class, 'intval'),
            initialKeys: self::parseInitialKeys($params['initial_key']),
            bpm: self::parseMinMaxOrList($params['bpm'], NumberMinMaxExpressionParser::class, 'floatval'),
            isFavorite: self::parseBoolean($params['is_favorite']),
            indexedDates: self::parseMinMaxOrList($params['indexed_date'], DateTimeMinMaxExpressionParser::class, 'intval'),
            pathname: $params['pathname'] ?? null,
        );
    }

    public function toSearchCriteria(): SearchCriteria
    {
        return new SearchCriteria(
            name: $this->name,
            guid: $this->guid ? Regex::exact($this->guid) : null,
            artist: $this->artist ? Regex::contains($this->artist) : null,
            title: $this->title ? Regex::contains($this->title) : null,
            genres: $this->genres ? array_map(fn ($g) => Regex::exact($g), $this->genres) : null,
            publishers: $this->publishers ? array_map(fn ($p) => Regex::containsWordPart($p), $this->publishers) : null,
            years: $this->years,
            initialKeys: $this->initialKeys,
            bpm: $this->bpm,
            isFavorite: $this->isFavorite ?: null,
            indexedDates: $this->indexedDates,
            pathname: $this->pathname,
        );
    }

    private static function parseString(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function parseBoolean(mixed $value): ?bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private static function parseCommaSeparated(?string $value): ?array
    {
        if (!$value) {
            return null;
        }

        $items = explode(',', $value);
        $items = array_map('trim', $items);
        $items = array_filter($items, fn ($item) => $item !== '');
        $items = array_unique($items);

        return $items !== [] ? array_values($items) : null;
    }

    private static function parseInitialKeys(?string $value): ?array
    {
        $keys = self::parseCommaSeparated($value);

        if (!$keys) {
            return null;
        }

        return array_map('strtoupper', $keys);
    }

    private static function parseMinMaxOrList(?string $value, string $parser, string $castFn): MinMaxInfo|array|null
    {
        if (!$value) {
            return null;
        }

        /** @var ExpressionParser $parser */
        $parsed = $parser::parse($value);

        if ($parsed) {
            return $parsed;
        }

        $items = self::parseCommaSeparated($value);

        if (!$items) {
            return null;
        }

        return array_map($castFn, $items);
    }
}
