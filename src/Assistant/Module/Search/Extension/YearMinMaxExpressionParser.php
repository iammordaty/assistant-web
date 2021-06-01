<?php

namespace Assistant\Module\Search\Extension;

final class YearMinMaxExpressionParser extends NumberMinMaxExpressionParser
{
    public static function parse(string $expression): ?MinMaxInfo
    {
        $value = self::normalizeExpression($expression);

        // "- value"

        $matches = [];
        $isMatched = preg_match('/^-(\d+)$/', $value, $matches) === 1;

        if ($isMatched) {
            $currentYear = (int) (new \DateTime())->format('Y');

            return MinMaxInfo::create([
                'gte' => $currentYear - (int) $matches[1],
                'lte' => $currentYear,
            ]);
        }

        return parent::parse($expression);
    }
}
