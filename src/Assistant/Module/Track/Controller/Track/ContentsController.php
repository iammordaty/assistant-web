<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Controller as BaseController;
use Assistant\Module\Track;

class ContentsController extends BaseController
{
    public function get($guid)
    {
        $track = (new Track\Repository\TrackRepository($this->app->container['db']))->findOneByGuid($guid);

        if ($track === null) {
            return $this->app->notFound();
        }

        $file = $this->app->container->parameters['collection']['indexer']['root_dir'] . $track->pathname;

        if (is_readable($file) === false) {
            return $this->app->notFound();
        }

        $response = $this->app->response();

        $response->header('Content-Type', 'audio/mpeg');
        $response->header('Content-Length', filesize($file));
        $response->header('Content-Disposition', sprintf('inline; filename="%s - %s"', $track->artist, $track->title));
        $response->header('Cache-Control', 'no-cache');

        readfile($file);

        $this->app->stop();
    }
}
