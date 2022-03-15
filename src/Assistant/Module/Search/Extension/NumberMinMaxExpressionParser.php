<?php

namespace Assistant\Module\Search\Extension;

class NumberMinMaxExpressionParser extends RawMinMaxExpressionParser
{
    public static function parse(string $expression): ?MinMaxInfo
    {
        $value = self::normalizeExpression($expression);

        // "value", ale nieco inaczej: zakładamy, że podając BPM = "125"
        // chodzi o BPM większy lub równy 125.0 i mniejszy od 126 (BPM w bazie zapisany jest jako float).

        if (is_numeric($value) && ctype_digit($value)) {
            return MinMaxInfo::create([
                'gte' => (float) $value,
                'lt' => (float) ($value + 1),
            ]);
        }

        $minMaxInfo = parent::parse($expression);

        if ($minMaxInfo === null) {
            return null;
        }

        $numbers = array_map(fn($value) => (float) $value, $minMaxInfo->values());
        $numberMinMaxInfo = array_combine($minMaxInfo->operators(), $numbers);

        return MinMaxInfo::create($numberMinMaxInfo);
    }
}
