<?php

use Fig\Http\Message\StatusCodeInterface;
use Monolog\Logger;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return static function (App $app): void {
    $app->addRoutingMiddleware();

    $app->add(TwigMiddleware::createFromContainer($app, containerKey: Twig::class));

    $errorMiddleware = $app->addErrorMiddleware(
        displayErrorDetails: true,
        logErrors: true,
        logErrorDetails: true,
        logger: $app->getContainer()->get(Logger::class),
    );

    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        fn () => $app->getResponseFactory()->createResponse(StatusCodeInterface::STATUS_NOT_FOUND)
    );
};
