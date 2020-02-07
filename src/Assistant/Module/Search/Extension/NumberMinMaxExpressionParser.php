<?php

namespace Assistant\Module\Search\Extension;

class NumberMinMaxExpressionParser extends RawMinMaxExpressionParser
{
    public static function parse(string $expression): ?array
    {
        $minMaxInfo = parent::parse($expression);

        $numbers = array_map(fn($value) => (int) $value, array_values($minMaxInfo));
        $numberMinMaxInfo = array_combine(array_keys($minMaxInfo), $numbers);

        return $numberMinMaxInfo;
    }
}
