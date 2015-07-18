<?php

namespace Assistant\Module\Dashboard\Controller;

use Assistant\Controller as BaseController;
use Assistant\Module\Dashboard;

class DashboardController extends BaseController
{
    public function index()
    {
        $repository = new Dashboard\Repository\DashboardRepository($this->app->container['db']);

        return $this->app->render(
            '@dashboard/index.twig',
            [
                'menu' => 'dashboard',
                'trackCountByGenre' => $repository->getTrackCountByGenre(),
                'trackCountByArtist' => $repository->getTrackCountByArtist(),
                'recentlyAddedTracks' => $repository->findBy([ ], [ ], [ 'limit' => 10, 'sort' => [ 'indexed_date' => -1 ] ]),
            ]
        );
    }
}
