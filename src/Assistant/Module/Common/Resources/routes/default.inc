<?php

use Assistant\Module\Common\Controller\LogController;
use Assistant\Module\Common\Controller\TaskController;
use Slim\App as App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/common', function (RouteCollectorProxy $group) {
        $group
            ->post('/task/calculate-audio-data', [ TaskController::class, 'calculateAudioData' ])
            ->setName('common.task.calculate');

        $group
            ->post('/task/remove-metadata', [ TaskController::class, 'removeMetadata' ])
            ->setName('common.task.remove');

        $group
            ->post('/task/clean-pathname', [ TaskController::class, 'cleanPathname' ])
            ->setName('common.task.clean');

        $group
            ->post('/task/move', [ TaskController::class, 'move' ])
            ->setName('common.task.move');

        $group
            ->get('/log', [ LogController::class, 'index' ])
            ->setName('common.log.index');

        $group
            ->get('/log/refresh', [ LogController::class, 'refresh' ])
            ->setName('common.log.refresh');
    });
};
