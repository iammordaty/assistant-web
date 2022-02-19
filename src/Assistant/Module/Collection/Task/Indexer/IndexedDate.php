<?php

namespace Assistant\Module\Collection\Task\Indexer;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use BackedEnum;
use DateTime;

final class IndexedDate
{
    public static function get(
        CollectionItemInterface $collectionItem,
        BackedEnum $strategy,
        ?DateTime $fixedIndexedDate,
    ): DateTime {
        $currentDate = new DateTime();

        if ($strategy === IndexingDateStrategy::CURRENT_DATE) {
            return $currentDate;
        }

        if ($strategy === IndexingDateStrategy::FIXED_DATE) {
            return $fixedIndexedDate;
        }

        //      1         2      3       4
        // /collection/Singles/2021/
        // /collection/Singles/2021/01. styczeń/

        $parts = explode(DIRECTORY_SEPARATOR, $collectionItem->getPathname());

        $year = trim($parts[3], '-');
        $month = isset($parts[4]) ? substr($parts[4], 0, 2) : null;

        $indexedDate = new DateTime();

        $indexedDate->setDate(
            $year,
            $month ?: $currentDate->format('m'),
            $currentDate->format('d')
        );

        return $indexedDate;
    }
}
