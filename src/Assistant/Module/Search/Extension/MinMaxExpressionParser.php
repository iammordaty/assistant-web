<?php

namespace Assistant\Module\Search\Extension;

class MinMaxExpressionParser
{
    /**
     * @param string $expression
     * @return array|null
     */
    public static function parse($expression)
    {
        $value = self::normalizeExpression($expression);

        // "value"

        if (is_numeric($value)) {
            return [
                'gte' => (int) $value,
                'lte' => (int) $value,
            ];
        }

        // "value" - "value"

        $matches = [];
        $isMatched = preg_match('/^(\d+)-(\d+)$/', $value, $matches) === 1;

        if ($isMatched) {
            return [
                'gte' => (int) $matches[1],
                'lte' => (int) $matches[2],
            ];
        }

        // ">= value", "> value", "<= value", "< value"

        $matches = [];
        $isMatched = preg_match('/^(?\'op\'[><])(?\'eq\'=)?(?\'val\'\d+)/', $value, $matches) === 1;

        if (!$isMatched) {
            return null;
        }

        $equal = $matches['eq'] === '=' ? 'e' : '';
        $value = (int) $matches['val'];

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

    /**
     * @param string $expression
     * @return string
     */
    protected static function normalizeExpression($expression)
    {
        $value = str_replace(' ', '', trim($expression));

        return $value;
    }
}