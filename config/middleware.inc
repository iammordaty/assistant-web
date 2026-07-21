<?php

use Assistant\Module\Common\Extension\Messages;
use Fig\Http\Message\StatusCodeInterface;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return static function (App $app): void {
    $app->addRoutingMiddleware();

    $app->add(TwigMiddleware::createFromContainer($app, containerKey: Twig::class));

    $app->add(function (
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ) use ($app): ResponseInterface {
        $container = $app->getContainer();

        $container
            ->get(Twig::class)
            ->getEnvironment()
            ->addGlobal('flash', $container->get(Messages::class)->all());

        return $handler->handle($request);
    });

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
