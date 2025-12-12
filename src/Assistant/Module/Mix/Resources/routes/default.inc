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
        ->get('/mix/create', [ MixController::class, 'create' ])
        ->setName('mix.mix.create');

    $app
        ->get('/mix/{guid}', [ MixController::class, 'view'])
        ->setName('mix.mix.view');

    $app
        ->post('/mix[/{guid}]', [ MixController::class, 'saveMix'])
        ->setName('mix.mix.save-mix');

    $app
        ->post('/mix/{guid}/attempt', [ MixController::class, 'saveAttempt'])
        ->setName('mix.mix.save-attempt');

    $app
        ->delete('/mix/{guid}', [ MixController::class, 'deleteMix'])
        ->setName('mix.mix.delete');
};
