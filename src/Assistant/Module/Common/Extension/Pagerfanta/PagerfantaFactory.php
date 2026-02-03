<?php

namespace Assistant\Module\Common\Extension\Pagerfanta;

use Assistant\Module\Search\Extension\Result\TrackSearchResult;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\NullAdapter;
use Pagerfanta\Pagerfanta;

final class PagerfantaFactory
{
    public static function createWithTrackSearchResult(TrackSearchResult $result): Pagerfanta
    {
        return self::createWithNullAdapter($result->total, $result->page, $result->limit);
    }

    public static function createWithNullAdapter(int $count, int $page, int $maxPerPage): Pagerfanta
    {
        $adapter = new NullAdapter($count);

        return self::createFromAdapter($adapter, $page, $maxPerPage);
    }

    public static function createFromAdapter(AdapterInterface $adapter, int $page, int $maxPerPage): Pagerfanta
    {
        $pagerfanta = Pagerfanta::createForCurrentPageWithMaxPerPage($adapter, $page, $maxPerPage);

        // $pagerfanta->setNormalizeOutOfRangePages(true);

        return $pagerfanta;
    }
}
