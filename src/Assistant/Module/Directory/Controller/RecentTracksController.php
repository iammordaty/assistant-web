<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;
use IntlDateFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class RecentTracksController
{
    private IntlDateFormatter $dateFormatter;

    public function __construct(
        private readonly TrackService $trackService,
        private readonly Twig $view,
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
            [ 'name' => $groupName, 'segments' => $groupSegments ] = $this->getGroup($track);

            if (!isset($recent[$groupName])) {
                $recent[$groupName] = [
                    'name' => $groupName,
                    'segments' => $groupSegments,
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

        $name = $year . '-' . $month;

        $segments = [
            [ 'name' => $year, 'date' => $year ],
            [ 'name' => ucfirst($month), 'date' => $date ],
        ];

        return [ 'name' => $name, 'segments' => $segments ];
    }
}
