<?php

namespace Assistant\Module\Search\Extension\Request\ExpressionParser;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;

interface ExpressionParser
{
    public static function parse(string $expression): ?MinMaxInfo;
}
