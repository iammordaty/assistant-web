<?php

/* @var $app Slim */

use Assistant\Module\Dashboard\Controller;
use Slim\Slim;

$app->get(
    '/',
    function () use ($app) {
        (new Controller\DashboardController($app))->index();
    }
)->name('dashboard');
