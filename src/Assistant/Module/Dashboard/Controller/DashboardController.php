<?php

namespace Assistant\Module\Dashboard\Controller;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Stats\Repository\StatsRepository;
use Slim\Slim;

final class DashboardController extends AbstractController
{
    private const MAX_RECENT_TRACKS = 10;

    private const MAX_GENRES = 10;

    private const MAX_ARTISTS = 10;

    private StatsRepository $statsRepository;

    public function __construct(Slim $app)
    {
        parent::__construct($app);

        $this->statsRepository = $app->container[StatsRepository::class];
    }

    public function index()
    {
        return $this->app->render('@dashboard/index.twig', [
            'menu' => 'dashboard',
            'trackCountByGenre' => $this->statsRepository->getTrackCountByGenre(self::MAX_GENRES),
            'trackCountByArtist' => $this->statsRepository->getTrackCountByArtist(self::MAX_ARTISTS),
            'recentlyAddedTracks' => $this->statsRepository->getRecentlyAddedTracks(self::MAX_RECENT_TRACKS),
        ]);
    }
}
