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
        $name = $this->name;
        $artist = null;
        $title = null;

        if ($this->name) {
            [ $name, $modifiers ] = self::parseNameWithModifiers($this->name);

            if ($modifiers !== []) {
                if (isset($modifiers['artist'])) {
                    $artist = Regex::contains($modifiers['artist']);
                }

                $titleTerm = $modifiers['title'] ?? null;
                $remixTerm = $modifiers['remix'] ?? null;

                if ($titleTerm && $remixTerm) {
                    $title = self::titleAndRemixRegex($titleTerm, $remixTerm);
                } elseif ($titleTerm) {
                    $title = self::titleOnlyRegex($titleTerm);
                } elseif ($remixTerm) {
                    $title = self::remixRegex($remixTerm);
                }
            }
        } else {
            $artist = $this->artist ? Regex::contains($this->artist) : null;
            $title = $this->title ? Regex::contains($this->title) : null;
        }

        return new SearchCriteria(
            name: $name,
            guid: $this->guid ? Regex::exact($this->guid) : null,
            artist: $artist,
            title: $title,
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

    /**
     * @return array{0:?string,1:array<string,string>}
     */
    private static function parseNameWithModifiers(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            return [ null, [] ];
        }

        $pattern = '/(?:(^|\s))(a|artist|t|title|r|remix)\s*:\s*/i';
        $matches = [];

        $matchCount = preg_match_all($pattern, $input, $matches, PREG_OFFSET_CAPTURE);

        if ($matchCount < 1) {
            return [ $input, [] ];
        }

        $modifiers = [];
        $freeText = trim(substr($input, 0, $matches[0][0][1]));
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            $keyRaw = strtolower($matches[2][$index][0]);
            $key = self::normalizeModifier($keyRaw);

            if ($key === null || isset($modifiers[$key])) {
                continue;
            }

            $valueStart = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $valueEnd = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($input);
            $value = trim(substr($input, $valueStart, $valueEnd - $valueStart));

            if ($value !== '') {
                $modifiers[$key] = $value;
            }
        }

        return [ $freeText !== '' ? $freeText : null, $modifiers ];
    }

    private static function normalizeModifier(string $key): ?string
    {
        return match ($key) {
            'a', 'artist' => 'artist',
            't', 'title' => 'title',
            'r', 'remix' => 'remix',

            default => null,
        };
    }

    private static function titleOnlyRegex(string $value): Regex
    {
        $escaped = preg_quote($value, '/');
        $pattern = '^[^\\[]*' . $escaped;

        return Regex::create($pattern, [ Regex::REGEX_CASE_INSENSITIVE ]);
    }

    private static function remixRegex(string $value): Regex
    {
        $escaped = preg_quote($value, '/');
        $pattern = '\\[[^\\]]*' . $escaped . '[^\\]]*\\]';

        return Regex::create($pattern, [ Regex::REGEX_CASE_INSENSITIVE ]);
    }

    private static function titleAndRemixRegex(string $title, string $remix): Regex
    {
        $titleEscaped = preg_quote($title, '/');
        $remixEscaped = preg_quote($remix, '/');
        $pattern = '^(?=[^\\[]*' . $titleEscaped . ')(?=.*\\[[^\\]]*' . $remixEscaped . '[^\\]]*\\]).*';

        return Regex::create($pattern, [ Regex::REGEX_CASE_INSENSITIVE ]);
    }
}
