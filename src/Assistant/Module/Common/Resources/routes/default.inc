<?php

/* @var $app \Slim\Slim */

use Assistant\Module\Common;

$app->group('/common', function () use ($app) {
    $app->post(
        '/task/calculate-audio-data',
        function () use ($app) {
            (new Common\Controller\TaskController($app))->calculateAudioData();
        }
    )->name('common.task.calculate');

    $app->post(
        '/task/move',
        function () use ($app) {
            (new Common\Controller\TaskController($app))->move();
        }
    )->name('common.task.move');
});
