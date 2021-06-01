<?php

use Assistant\Module\Search\Controller\AdvancedSearchController;
use Assistant\Module\Search\Controller\SimpleSearchController;
use Slim\App as App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/search', function (RouteCollectorProxy $group) {
        $group
            ->get('/simple', [ SimpleSearchController::class, 'index' ])
            ->setName('search.simple.index');

        $group
            ->get('/advanced', [ AdvancedSearchController::class, 'index' ])
            ->setName('search.advanced.index');
    });
};
