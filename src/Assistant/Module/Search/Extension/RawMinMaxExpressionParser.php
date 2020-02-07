<?php

namespace Assistant\Module\Search\Extension;

class RawMinMaxExpressionParser
{
    public static function parse(string $expression): ?array
    {
        $value = self::normalizeExpression($expression);

        // "value"

        if (is_numeric($value)) {
            return [
                'gte' => $value,
                'lte' => $value,
            ];
        }

        // "value" - "value"

        $matches = [];
        $isMatched = preg_match('/^([\w.]+)-([\w.]+)$/', $value, $matches) === 1;

        if ($isMatched) {
            return [
                'gte' => $matches[1],
                'lte' => $matches[2],
            ];
        }

        // ">= value", "> value", "<= value", "< value"

        $matches = [];
        $isMatched = preg_match('/^(?\'op\'[><])(?\'eq\'=)?(?\'val\'[\w.]+)/', $value, $matches) === 1;

        if (!$isMatched) {
            return null;
        }

        $equal = $matches['eq'] === '=' ? 'e' : '';
        $value = $matches['val'];

        // ">= value", "> value"

        if ($matches['op'] === '>') {
            return [
                "gt{$equal}" => $value,
                'lte' => null,
            ];
        }

        // "<= value", "< value"

        return [
            'gt' => null,
            "lt{$equal}" => $value,
        ];
    }

    protected static function normalizeExpression(string $expression): string
    {
        $normalizedExpression = str_replace(' ', '', trim($expression));

        return $normalizedExpression;
    }
}