<?php

namespace Assistant\Module\Search\Extension;

class MinMaxExpressionInfoToDbQuery
{
    /**
     * @param array $minMaxInfo
     * @return array|int
     */
    public static function convert(array $minMaxInfo)
    {
        $minMaxInfoValues = array_values($minMaxInfo);

        if ($minMaxInfoValues[0] === $minMaxInfoValues[1]) {
            return $minMaxInfoValues[0];
        }

        $query = [];

        foreach ($minMaxInfo as $key => $value) {
            if ($value) {
                $query['$' . $key] = $value;
            }
        }

        return $query;
    }
}