<?php

use Assistant\Module\Track\Controller\Track\AutocompleteController;
use Assistant\Module\Track\Controller\Track\ClassifierMetadataController;
use Assistant\Module\Track\Controller\Track\ContentsController;
use Assistant\Module\Track\Controller\Track\EditController as TrackEditController;
use Assistant\Module\Track\Controller\IncomingTrack\EditController as IncomingTrackEditController;
use Assistant\Module\Track\Controller\TrackController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/track', function (RouteCollectorProxy $group) {
        $group->get('/autocomplete', AutocompleteController::class)->setName('track.track.autocomplete');

        $group->get('/{guid}', [ TrackController::class, 'index' ])->setName('track.track.index');

        $group->post('/{guid}/favorite', [ TrackController::class, 'favorite' ])->setName('track.track.favorite');

        $group
            ->get('/{guid}/classifier-metadata', [ ClassifierMetadataController::class, 'get' ])
            ->setName('track.classifier-metadata.get');

        $group->get('/{pathname:.*}/contents', [ ContentsController::class, 'get' ])->setName('track.contents.get');

        $group->get('/{pathname:.*}/edit', [ TrackEditController::class, 'edit' ])->setName('track.edit.edit');

        $group->post('/{pathname:.*}/save', [ TrackEditController::class, 'save' ])->setName('track.edit.save');
    });

    $app->group('/incoming-track', function (RouteCollectorProxy $group) {
        $group
            ->get('/{pathname:.*}/edit', [ IncomingTrackEditController::class, 'edit' ])
            ->setName('incoming-track.edit.edit');

        $group
            ->post('/{pathname:.*}/save', [ IncomingTrackEditController::class, 'save' ])
            ->setName('incoming-track.edit.save');
    });
};
