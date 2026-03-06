<?php

namespace Assistant\Module\Search\Extension\Request\ExpressionParser;

interface ExpressionParser
{
    public static function parse(string $expression): mixed;
}
