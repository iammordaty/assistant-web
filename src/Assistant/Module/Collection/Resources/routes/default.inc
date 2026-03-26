<?php

use Assistant\Module\Collection\Controller\CollectionStatusController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/collection', function (RouteCollectorProxy $group) {
        $group
            ->get('/status', [CollectionStatusController::class, 'index'])
            ->setName('collection.status.index');

        $group
            ->post('/status/toggle-ignore', [CollectionStatusController::class, 'toggleIgnore'])
            ->setName('collection.status.toggle-ignore');

        $group
            ->get('/status/{type}', [CollectionStatusController::class, 'detail'])
            ->setName('collection.status.detail');
    });
};
