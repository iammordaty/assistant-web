<?php

/* @var $app \Slim\Slim */

use Assistant\Module\Common;
use Assistant\Module\Common\Controller\LogController;

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

    $app->post(
        '/task/clean',
        function () use ($app) {
            (new Common\Controller\TaskController($app))->clean();
        }
    )->name('common.task.clean');

    $app->get(
        '/log',
        function () use ($app) {
            (new LogController($app))->index();
        }
    )->name('common.log.index');

    $app->get(
        '/log/ajax',
        function () use ($app) {
            (new LogController($app))->ajax();
        }
    )->name('common.log.ajax');
});
