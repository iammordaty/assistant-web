<?php

namespace Assistant\Module\Common\Storage;

use Assistant\Module\Search\Extension\MinMaxInfo;
use MongoDB\BSON\UTCDateTime;
use DateTimeInterface;

final class MinMaxInfoToStorageQuery
{
    public static function toStorage(MinMaxInfo $minMaxInfo): array|int
    {
        if ($minMaxInfo->isEqual()) {
            $minMaxInfoValues = $minMaxInfo->values();

            return $minMaxInfoValues[0];
        }

        $query = [];

        // podzielić na dwie fazy: filtrowanie i mapowanie

        foreach ($minMaxInfo->get() as $key => $value) {
            if (!$value) {
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $value = new UTCDateTime($value);
            }

            $query['$' . $key] = $value;
        }

        return $query;
    }
}
