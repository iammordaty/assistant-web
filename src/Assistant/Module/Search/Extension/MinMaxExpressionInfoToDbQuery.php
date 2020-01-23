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
        $filtered = array_merge(array_unique($minMaxInfoValues));

        if (count($filtered) === 1) {
            return $filtered[0];
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