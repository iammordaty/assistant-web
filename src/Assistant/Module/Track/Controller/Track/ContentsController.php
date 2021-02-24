<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;

class ContentsController extends AbstractController
{
    public function get($guid)
    {
        /** @var Track $track */
        $track = (new TrackRepository($this->app->container['db']))->findOneByGuid($guid);

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
