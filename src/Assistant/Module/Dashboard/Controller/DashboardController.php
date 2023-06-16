<?php

namespace Assistant\Module\Dashboard\Controller;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Search\Extension\SearchCriteria;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Repository\TrackStatsRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final readonly class DashboardController
{
    private const MAX_ARTISTS = 10;
    private const MAX_GENRES = 10;
    private const MAX_RANDOM_TRACKS = 30;
    private const MAX_RECENT_TRACKS = 15;

    public function __construct(
        private Config $config,
        private ReaderFacade $reader,
        private TrackService $trackService,
        private TrackSearchService $searchService,
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
            'trackCount' => $this->searchService->count(new SearchCriteria()),
            'incomingTracks' => $this->getIncomingTracks(),
            'randomTracks' => $this->trackService->getRandom(self::MAX_RANDOM_TRACKS),
            'recentlyAddedTracks' => $this->trackService->getRecent(limit: self::MAX_RECENT_TRACKS),
        ]);
    }

    private function getIncomingTracks(): array
    {
        /**
         * @idea To jest z grubsza to samo co w IncomingTracksController::getCollectionItems(),
         *       więc fajnie byłoby to uspójnić i wyciągnąć z kontrolerów
         */

        $tracks = [];

        $nodes = Finder::create([
            'pathname' => $this->config->get('collection.incoming_dir'),
            'recursive' => false,
            'skip_self' => true,
            'mode' => Finder::MODE_FILES_ONLY
        ]);

        foreach ($nodes as $node) {
            /** @var CollectionItemInterface $collectionItem */
            $collectionItem = $this->reader->read($node);
            $tracks[] = $collectionItem;
        }

        usort($tracks, static fn (IncomingTrack $track1, IncomingTrack $track2): int => (
            -1 * ($track1->getFile()->getMTime() <=> $track2->getFile()->getMTime())
        ));

        return $tracks;
    }
}
