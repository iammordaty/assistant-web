<?php

use Assistant\Module\Mix;
use Assistant\Module\Mix\Controller\MixController;
use Assistant\Module\Mix\Controller\ListController;
use Assistant\Module\Mix\Controller\HarmonicMixController;
use Slim\App;

return static function (App $app): void {
    $app
        ->get('/mix/list', [ ListController::class, 'index' ])
        ->setName('mix.list.index');

    $app
        ->map(['GET', 'POST'], '/mix/harmonize', [ HarmonicMixController::class, 'index' ])
        ->setName('mix.harmonize.index');

    $app
        ->map(['GET', 'POST'], '/mix/{guid}', [ MixController::class, 'index' ])
        ->setName('mix.edit.index');

    $app
        ->post('/mix/{guid}/save', [ MixController::class, 'saveAttempt'])
        ->setName('mix.edit.save');

    $app
        ->post('/mix/{guid}/properties', [ MixController::class, 'saveMixProperties'])
        ->setName('mix.edit.properties');
};
