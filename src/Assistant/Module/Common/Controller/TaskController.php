<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common;
use Assistant\Module\Directory;
use Assistant\Module\Collection;
use Assistant\Module\Track;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\IgnoredPathIterator;
use Assistant\Module\File\Extension\SplFileInfo;

class TaskController extends Common\Controller\AbstractController
{
    use Common\Extension\Traits\GetTargetPath;

    public function calculateAudioData()
    {
        $pathname = $this->app->request()->post('pathname');

        // TODO: "/data" configa
        // TODO: "/collection" configa

        (new \Cocur\BackgroundProcess\BackgroundProcess(
            sprintf('php /data/app/console.php track:calculate-audio-data -w "/collection/%s"', ltrim($pathname, '/'))
        ))->run();
    }

    public function move()
    {
        $data = $this->app->request->post();

        // TODO: "/collection" z configa

        $absolutePathname = sprintf('/collection%s', $data['pathname']);
        $absoluteTargetPathname = sprintf('/collection%s', $data['targetPathname']);

        echo $absolutePathname, PHP_EOL;
        echo $absoluteTargetPathname, PHP_EOL;

        // TODO: "/data" z configa

        $r = (new \Cocur\BackgroundProcess\BackgroundProcess(
            $cmdString = sprintf(
                'php /data/app/console.php collection:move %s %s',
                escapeshellarg($absolutePathname),
                escapeshellarg($absoluteTargetPathname)
            )
        ))->run();

        echo $cmdString, PHP_EOL;
        echo $r ? 'ok' : 'nie ok';
    }
}
