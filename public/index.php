<?php

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;

setlocale(LC_TIME, 'pl_PL.utf8');

define('BASE_DIR', dirname(__DIR__));

if (function_exists('xdebug_set_filter')) {
    xdebug_set_filter(XDEBUG_FILTER_STACK, XDEBUG_PATH_EXCLUDE, [ BASE_DIR . '/vendor/' ]);
}

require_once BASE_DIR . '/vendor/autoload.php';

$config = (require_once BASE_DIR . '/config/config.inc')(BASE_DIR);

/** @noinspection PhpUnhandledExceptionInspection */
$container = (new ContainerBuilder())
    ->addDefinitions((require_once BASE_DIR . '/config/container.inc')(BASE_DIR, $config))
    ->build();

$app = Bridge::create($container);

(require_once BASE_DIR . '/config/routes.inc')($app);
(require_once BASE_DIR . '/config/middleware.inc')($app);

unset($config, $container);

$app->run();
