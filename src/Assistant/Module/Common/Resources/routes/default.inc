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
            ->post('/task/move', [ TaskController::class, 'move' ])
            ->setName('common.task.move');

        $group
            ->get('/log', [ LogController::class, 'index' ])
            ->setName('common.log.index');

        $group
            ->get('/log/ajax', [ LogController::class, 'ajax' ])
            ->setName('common.log.ajax');
    });
};
