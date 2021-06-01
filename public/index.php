<?php

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;

define('BASE_DIR', dirname(__DIR__));

require_once BASE_DIR . '/vendor/autoload.php';

$config = (require_once BASE_DIR . '/app/config.inc')(BASE_DIR);

/** @noinspection PhpUnhandledExceptionInspection */
$container = (new ContainerBuilder())
    ->addDefinitions((require_once BASE_DIR . '/app/container.inc')(BASE_DIR, $config))
    ->build();

$app = Bridge::create($container);

(require_once BASE_DIR . '/app/middleware.inc')($app);
(require_once BASE_DIR . '/app/routes.inc')($app);

unset($config, $container);

$app->run();
