<?php

/* @var $app \Slim\Slim */

use Assistant\Module\Mix;

$app->group('/mix', function () use ($app) {

    $app->map(
        '/',
        function () use ($app) {
            (new Mix\Controller\MixController($app))->index();
        }
    )->via('GET', 'POST')->name('mix.mix.index');
});
