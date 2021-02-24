<?php

namespace Assistant\Module\Common\Controller;

use Cocur\BackgroundProcess\BackgroundProcess;

final class TaskController extends AbstractController
{
    public function calculateAudioData(): void
    {
        $pathname = $this->app->request()->post('pathname');
        $command = sprintf(
            'php %s/app/console.php track:calculate-audio-data -w "%s"',
            $this->app->config('base_dir'),
            $pathname
        );

        (new BackgroundProcess($command))->run();
    }

    public function move(): void
    {
        $data = $this->app->request->post();

        echo $data['targetPathname'];

        /*
        (new BackgroundProcess(
            sprintf(
                'php /data/app/console.php collection:move %s %s',
                escapeshellarg($data['pathname']),
                escapeshellarg($targetPathname)
            )
        ))->run();
        */
    }
}
