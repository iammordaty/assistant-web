<?php

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;

/* @noinspection DuplicatedCode */
setlocale(LC_TIME, 'pl_PL.utf8');

$baseDir = dirname(__DIR__);

if (function_exists('xdebug_set_filter')) {
    xdebug_set_filter(XDEBUG_FILTER_STACK, XDEBUG_PATH_EXCLUDE, [ $baseDir . '/vendor/' ]);
}

require_once $baseDir . '/vendor/autoload.php';

$config = (require_once $baseDir . '/config/config.inc')($baseDir);

/** @noinspection PhpUnhandledExceptionInspection */
$container = (new ContainerBuilder())
    ->addDefinitions((require_once $baseDir . '/config/container.inc')($baseDir, $config))
    ->build();

$app = Bridge::create($container);

(require_once $baseDir . '/config/routes.inc')($baseDir, $app);
(require_once $baseDir . '/config/middleware.inc')($app);

unset($baseDir, $config, $container);

$app->run();
