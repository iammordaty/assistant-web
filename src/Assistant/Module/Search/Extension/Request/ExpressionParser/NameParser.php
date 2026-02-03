<?php

namespace Assistant\Module\Search\Extension\Request\ExpressionParser;

use Assistant\Module\Search\Extension\Criteria\Regex;

final class NameParser implements ExpressionParser
{
    public static function parse(string $expression): ?NameParseResult
    {
        $input = trim($expression);

        if ($input === '') {
            return null;
        }

        $matches = [];

        $matchCount = preg_match_all('/(?:(^|\s))(a|artist|t|title|r|remix)\s*:\s*/i', $input, $matches, PREG_OFFSET_CAPTURE);

        if ($matchCount < 1) {
            return new NameParseResult($input, []);
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

        return new NameParseResult($freeText !== '' ? $freeText : null, $modifiers);
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

    public static function titleOnlyRegex(string $value): Regex
    {
        $escaped = preg_quote($value, '/');
        $pattern = '^[^\\[]*' . $escaped;

        return Regex::create($pattern, [ Regex::REGEX_CASE_INSENSITIVE ]);
    }

    public static function remixRegex(string $value): Regex
    {
        $escaped = preg_quote($value, '/');
        $pattern = '\\[[^\\]]*' . $escaped . '[^\\]]*\\]';

        return Regex::create($pattern, [ Regex::REGEX_CASE_INSENSITIVE ]);
    }

    public static function titleAndRemixRegex(string $title, string $remix): Regex
    {
        $titleEscaped = preg_quote($title, '/');
        $remixEscaped = preg_quote($remix, '/');
        $pattern = '^(?=[^\\[]*' . $titleEscaped . ')(?=.*\\[[^\\]]*' . $remixEscaped . '[^\\]]*\\]).*';

        return Regex::create($pattern, [ Regex::REGEX_CASE_INSENSITIVE ]);
    }
}
