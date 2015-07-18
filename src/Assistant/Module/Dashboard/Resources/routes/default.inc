<?php

/* @var $app \Slim\Slim */

use Assistant\Module\Dashboard\Controller;

$app->get(
    '/',
    function () use ($app) {
        (new Controller\DashboardController($app))->index();
    }
)->name('dashboard');
