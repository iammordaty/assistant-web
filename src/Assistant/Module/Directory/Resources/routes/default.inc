<?php

/* @var $app Slim */

use Assistant\Module\Directory\Controller\BrowseController;
use Slim\Slim;

$app->group('/directory', function () use ($app) {
    $app->get(
        '/browse/recent',
        function () use ($app) {
            (new BrowseController($app))->recent();
        }
    )->name('directory.browse.recent');

    // TODO: /browse/incoming/(:guid+)
    //      (ocb? chodzi o /browse/incoming/(:pathname+) ?
    $app->get(
        '/browse/incoming(:pathname+)',
        function ($pathnameParts = null) use ($app) {
            $pathname = $pathnameParts ? implode(DIRECTORY_SEPARATOR, $pathnameParts) : null;

            (new BrowseController($app))->incoming($pathname);
        }
    )->name('directory.browse.incoming');

    $app->get(
        '/(:guid+)',
        function ($guidParts = null) use ($app) {
            $guid = $guidParts ? implode(DIRECTORY_SEPARATOR, $guidParts) : null;

            (new BrowseController($app))->index($guid);
        }
    )->name('directory.browse.index');
});
