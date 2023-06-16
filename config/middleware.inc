<?php

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

    $errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
        \Psr\Http\Message\ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) {
        /**
         * To jest tymczasowy fix na to, aby w logach nie pojawiały się błędy 404
         *
         * @fixme Zwracać obiekt typu response
         */
        return exit(404);
    });
};
