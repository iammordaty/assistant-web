<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Track\Repository\TrackRepository;
use Slim\Slim;

class ContentsController extends AbstractController
{
    private TrackRepository $trackRepository;

    public function __construct(Slim $app)
    {
        parent::__construct($app);

        $this->trackRepository = $app->container[TrackRepository::class];
    }

    public function get($guid)
    {
        $track = $this->trackRepository->getByGuid($guid);

        if (!$track || !is_readable($track->getPathname())) {
            return $this->app->notFound();
        }

        $response = $this->app->response();

        $response->header('Cache-Control', 'no-cache');
        $response->header('Content-Type', 'audio/mpeg');
        $response->header('Content-Length', filesize($track->getPathname()));
        $response->header(
            'Content-Disposition',
            sprintf('inline; filename="%s - %s"', $track->getArtist(), $track->getTitle())
        );

        readfile($track->getPathname());

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->app->stop();
    }
}
