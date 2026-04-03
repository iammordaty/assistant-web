<?php

namespace Assistant\Module\Search\Extension\Criteria;

use DateTime;

final class SearchCriteriaFacade
{
    public static function createFromName(string $name): SearchCriteria
    {
        return new SearchCriteria(name: $name);
    }

    public static function createFromGuid(array|Regex|string $guid): SearchCriteria
    {
        return new SearchCriteria(guid: $guid);
    }

    public static function createFromParent(string $parent): SearchCriteria
    {
        return new SearchCriteria(parent: $parent);
    }

    public static function createFromPathname(Regex|string $pathname): SearchCriteria
    {
        return new SearchCriteria(pathname: [ $pathname ]);
    }

    public static function createFromMinIndexedDate(DateTime $indexedDate): SearchCriteria
    {
        $indexedDates = MinMaxInfo::create([ 'gte' => $indexedDate, 'lte' => null ]);

        return new SearchCriteria(indexedDates: $indexedDates);
    }
}
