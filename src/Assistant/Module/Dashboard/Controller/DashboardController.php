<?php

namespace Assistant\Module\Dashboard\Controller;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Stats\Repository\StatsRepository;
use Assistant\Module\Track\Repository\TrackRepository;

class DashboardController extends AbstractController
{
    public function index()
    {
        $repository = new StatsRepository($this->app->container['db']);

        return $this->app->render(
            '@dashboard/index.twig',
            [
                'menu' => 'dashboard',
                'trackCountByGenre' => $repository->getTrackCountByGenre(),
                'trackCountByArtist' => $repository->getTrackCountByArtist(),
                'recentlyAddedTracks' => $repository->getRecentlyAddedTracks(),
            ]
        );
    }
}
