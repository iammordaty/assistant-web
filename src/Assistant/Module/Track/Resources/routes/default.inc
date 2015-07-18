<?php

/* @var $app \Slim\Slim */

use Assistant\Module\Track;

$app->group('/track', function () use ($app) {

    $app->get(
        '/:guid',
        function ($guid) use ($app) {
            (new Track\Controller\TrackController($app))->index($guid);
        }
    )->name('track.track.index');

    $app->get(
        '/:guid/contents',
        function ($guid) use ($app) {
            (new Track\Controller\Track\ContentsController($app))->get($guid);
        }
    )->name('track.contents.get');
});
