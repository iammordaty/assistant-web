<?php

namespace Assistant\Module\Dashboard\Controller;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Repository\TrackStatsRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final readonly class DashboardController
{
    private const int MAX_ARTISTS = 10;
    private const int MAX_GENRES = 10;
    private const int MAX_RANDOM_TRACKS = 30;
    private const int MAX_RECENT_TRACKS = 15;

    public function __construct(
        private Config $config,
        private ReaderFacade $reader,
        private TrackSearchService $searchService,
        private TrackStatsRepository $statsRepository,
        private Twig $view,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $trackCount = $this->searchService->count(new SearchCriteria());

        if ($trackCount === 0) {
            return $this->view->render($response, '@dashboard/welcome.twig', [
                'menu' => 'dashboard',
            ]);
        }

        return $this->view->render($response, '@dashboard/index.twig', [
            'menu' => 'dashboard',
            'trackCountByGenre' => $this->statsRepository->getTrackCountByGenre(self::MAX_GENRES),
            'trackCountByArtist' => $this->statsRepository->getTrackCountByArtist(self::MAX_ARTISTS),
            'trackCount' => $trackCount,
            'incomingTracks' => $this->getIncomingTracks(),
            'randomTracks' => $this->searchService->getRandom(limit: self::MAX_RANDOM_TRACKS),
            'recentlyAddedTracks' => $this->searchService->findRecent(limit: self::MAX_RECENT_TRACKS),
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
