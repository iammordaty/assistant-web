<?php

namespace Assistant\Module\Dashboard\Controller;

use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Repository\TrackStatsRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class DashboardController
{
    private const MAX_RECENT_TRACKS = 10;
    private const MAX_GENRES = 10;
    private const MAX_ARTISTS = 10;

    public function __construct(
        private TrackService $trackService,
        private TrackStatsRepository $statsRepository,
        private Twig $view,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, '@dashboard/index.twig', [
            'menu' => 'dashboard',
            'trackCountByGenre' => $this->statsRepository->getTrackCountByGenre(self::MAX_GENRES),
            'trackCountByArtist' => $this->statsRepository->getTrackCountByArtist(self::MAX_ARTISTS),
            'recentlyAddedTracks' => $this->trackService->getRecent(limit: self::MAX_RECENT_TRACKS),
        ]);
    }
}
