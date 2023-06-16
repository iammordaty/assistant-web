<?php

use Assistant\Module\Track\Controller\Track\AutocompleteController;
use Assistant\Module\Track\Controller\Track\ContentsController;
use Assistant\Module\Track\Controller\Track\EditController;
use Assistant\Module\Track\Controller\TrackController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/track', function (RouteCollectorProxy $group) {

        $group
            ->get('/autocomplete', AutocompleteController::class)
            ->setName('track.track.autocomplete');

        $group
            ->get('/{guid}', [ TrackController::class, 'index' ])
            ->setName('track.track.index');

        $group
            ->get('/{pathname:.*}/contents', [ ContentsController::class, 'get' ])
            ->setName('track.contents.get');

        $group
            ->get('/{pathname:.*}/edit', [ EditController::class, 'edit' ])
            ->setName('track.edit.edit');

        $group
            ->post('/{pathname:.*}/save', [ EditController::class, 'save' ])
            ->setName('track.edit.save');
    });
};
