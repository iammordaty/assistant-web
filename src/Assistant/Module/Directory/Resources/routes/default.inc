<?php

/* @var $app \Slim\Slim */

use Assistant\Module\Directory;

$app->group('/directory', function () use ($app) {
    $app->get(
        '/browse/recent',
        function () use ($app) {
            (new Directory\Controller\BrowseController($app))->recent();
        }
    )->name('directory.browse.recent');

    $app->get(
        '/(:guid+)',
        function ($guidParts = null) use ($app) {
            $guid = $guidParts ? implode(DIRECTORY_SEPARATOR, $guidParts) : null;

            (new Directory\Controller\BrowseController($app))->index($guid);
        }
    )->name('directory.browse.index');
});
