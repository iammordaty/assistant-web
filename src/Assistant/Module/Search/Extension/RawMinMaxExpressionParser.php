<?php

namespace Assistant\Module\Search\Extension;

class RawMinMaxExpressionParser
{
    public static function parse(string $expression): ?MinMaxInfo
    {
        $value = self::normalizeExpression($expression);

        // "value"

        if (is_numeric($value)) {
            return MinMaxInfo::create([
                'gte' => $value,
                'lte' => $value,
            ]);
        }

        // "value" - "value"

        $matches = [];
        $isMatched = preg_match('/^([\d.-\/]+)-([\d.-\/]+)$/', $value, $matches) === 1;

        if ($isMatched) {
            return MinMaxInfo::create([
                'gte' => $matches[1],
                'lte' => $matches[2],
            ]);
        }

        // ">= value", "> value", "<= value", "< value"

        $matches = [];
        $isMatched = preg_match('/^(?\'op\'[><])(?\'eq\'=)?(?\'val\'[\d.-\/]+)/', $value, $matches) === 1;

        if (!$isMatched) {
            return null;
        }

        $equal = $matches['eq'] === '=' ? 'e' : '';
        $value = $matches['val'];

        // ">= value", "> value"

        if ($matches['op'] === '>') {
            return MinMaxInfo::create([
                "gt{$equal}" => $value,
                'lte' => null,
            ]);
        }

        // "<= value", "< value"

        return MinMaxInfo::create([
            'gt' => null,
            "lt{$equal}" => $value,
        ]);
    }

    protected static function normalizeExpression(string $expression): string
    {
        $normalizedExpression = str_replace(' ', '', trim($expression));

        return $normalizedExpression;
    }
}
