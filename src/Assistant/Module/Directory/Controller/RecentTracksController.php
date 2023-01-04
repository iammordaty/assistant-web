<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Common\Extension\Breadcrumbs\Breadcrumb;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;
use IntlDateFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final readonly class RecentTracksController
{
    private IntlDateFormatter $dateFormatter;

    public function __construct(
        private TrackService $trackService,
        private Twig $view,
    ) {
        $this->dateFormatter = new IntlDateFormatter(
            locale: 'pl_PL.utf8',
            dateType: IntlDateFormatter::LONG,
            pattern: 'LLLL'
        );
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $recent = [];

        foreach ($this->trackService->getRecent() as $track) {
            [ 'name' => $groupName, 'breadcrumbs' => $breadcrumbs ] = $this->getGroup($track);

            if (!isset($recent[$groupName])) {
                $recent[$groupName] = [
                    'breadcrumbs' => $breadcrumbs,
                    'tracks' => [],
                ];
            }

            $recent[$groupName]['tracks'][] = $track;
        }

        return $this->view->render($response, '@directory/recent.twig', [
            'menu' => 'browse',
            'recent' => $recent,
        ]);
    }

    private function getGroup(Track $track): array
    {
        $date = $track->getIndexedDate()->format('m.Y');
        $month = $this->dateFormatter->format($track->getIndexedDate());
        $year = $track->getIndexedDate()->format('Y');

        $route = Route::create('search.advanced.index');

        $breadcrumbs = [
            new Breadcrumb(Route::create('directory.browse.index')),
            new Breadcrumb($route->withQuery([ 'indexed_date' => $year ]), $year),
            new Breadcrumb($route->withQuery([ 'indexed_date' => $date ]), ucfirst($month)),
        ];

        $name = $year . '-' . $month;

        return [ 'name' => $name, 'breadcrumbs' => $breadcrumbs ];
    }
}
