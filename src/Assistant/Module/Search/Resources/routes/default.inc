<?php

/* @var $app Slim */

use Assistant\Module\Search\Controller;
use Slim\Slim;

$app->group('/search', function () use ($app) {

    $app->get(
        '/simple',
        function () use ($app) {
            (new Controller\SimpleSearchController($app))->index();
        }
    )->name('search.simple.index');

    $app->get(
        '/advanced',
        function () use ($app) {
            (new Controller\AdvancedSearchController($app))->index();
        }
    )->name('search.advanced.index');
});
