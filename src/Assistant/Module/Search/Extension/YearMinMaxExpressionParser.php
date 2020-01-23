<?php

namespace Assistant\Module\Search\Extension;

class YearMinMaxExpressionParser extends MinMaxExpressionParser
{
    /**
     * @inheritDoc
     */
    public static function parse($expression)
    {
        $value = self::normalizeExpression($expression);

        // "- value"

        $matches = [];
        $isMatched = preg_match('/^-(\d+)$/', $value, $matches) === 1;

        if ($isMatched) {
            $currentYear = (int) (new \DateTime())->format('Y');

            return [
                'gte' => $currentYear - (int) $matches[1],
                'lte' => $currentYear,
            ];
        }

        return parent::parse($expression);
    }
}