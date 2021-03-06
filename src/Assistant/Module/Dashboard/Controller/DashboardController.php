<?php

namespace Assistant\Module\Dashboard\Controller;

use Assistant\Module\Track\Repository\TrackStatsRepository;
use Assistant\Module\Track\Repository\TrackRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class DashboardController
{
    private const MAX_RECENT_TRACKS = 10;
    private const MAX_GENRES = 10;
    private const MAX_ARTISTS = 10;

    public function __construct(
        private TrackStatsRepository $statsRepository,
        private TrackRepository $trackRepository,
        private Twig $view,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->view->render($response, '@dashboard/index.twig', [
            'menu' => 'dashboard',
            'trackCountByGenre' => $this->statsRepository->getTrackCountByGenre(self::MAX_GENRES),
            'trackCountByArtist' => $this->statsRepository->getTrackCountByArtist(self::MAX_ARTISTS),
            'recentlyAddedTracks' => $this->trackRepository->getRecent(limit: self::MAX_RECENT_TRACKS),
        ]);
    }
}
