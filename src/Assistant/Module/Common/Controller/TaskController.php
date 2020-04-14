<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common;
use Cocur\BackgroundProcess\BackgroundProcess;

class TaskController extends Common\Controller\AbstractController
{
    public function calculateAudioData()
    {
        $pathname = $this->app->request()->post('pathname');

        // TODO: "/data" configa
        // TODO: "/collection" configa

        (new BackgroundProcess(
            sprintf('php /data/app/console.php track:calculate-audio-data -w "/collection/%s"', ltrim($pathname, '/'))
        ))->run();
    }

    public function clean()
    {
        $pathname = $this->app->request()->post('pathname');

        // TODO: "/data" configa
        // TODO: "/collection" configa

        (new BackgroundProcess(
            sprintf('php /data/app/console.php track:clean -r "/collection/%s"', ltrim($pathname, '/'))
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

        $r = (new BackgroundProcess(
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
