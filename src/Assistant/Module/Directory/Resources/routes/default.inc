<?php

/** @var App $app */

use Assistant\Module\Directory\Controller\BrowseController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/directory', function (RouteCollectorProxy $group) {
        $group
            ->get('/browse/recent', [ BrowseController::class, 'recent' ])
            ->setName('directory.browse.recent');

        $group
            ->get('/browse/incoming/[{pathname:.*}]', [ BrowseController::class, 'incoming' ])
            ->setName('directory.browse.incoming');

        // TODO: Ujednolicić poprzez dodanie prefiksu "browse", tj. /browse/[{guid:.*}],
        //       a następnie spróbować przenieść "nad" "/browse/recent"

        $group
            ->get('/[{guid:.*}]', [ BrowseController::class, 'index' ])
            ->setName('directory.browse.index');
    });
};
