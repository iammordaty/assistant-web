<?php

namespace Assistant\Module\Common\Extension\Pagerfanta;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\NullAdapter;
use Pagerfanta\Pagerfanta;

final class PagerfantaFactory
{
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
