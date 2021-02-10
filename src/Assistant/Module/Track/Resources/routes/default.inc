<?php

/* @var $app Slim */

use Assistant\Module\Track\Controller\Track\ContentsController;
use Assistant\Module\Track\Controller\Track\EditController;
use Assistant\Module\Track\Controller\TrackController;
use Slim\Slim;

$app->group('/track', function () use ($app) {

    $app
        ->get('/:guid', fn($guid) => (new TrackController($app))->index($guid))
        ->name('track.track.index');

    $app
        ->get('/:guid/contents', fn($guid) => (new ContentsController($app))->get($guid))
        ->name('track.contents.get');

    $app
        ->get('/:pathname/edit', fn($pathname) => (new EditController($app))->edit($pathname))
        ->name('track.edit.edit');

    $app
        ->post('/:pathname/save', fn($pathname) => (new EditController($app))->save($pathname))
        ->name('track.edit.save');
});
