<?php

use Monolog\Logger;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return static function (App $app): void {
    $app->add(TwigMiddleware::createFromContainer($app, containerKey: Twig::class));

    $app->addErrorMiddleware(
        displayErrorDetails: true,
        logErrors: true,
        logErrorDetails: true,
        logger: $app->getContainer()->get(Logger::class),
    );
};
