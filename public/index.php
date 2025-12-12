<?php

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Kint\Renderer\RichRenderer as KinkRichRenderer;

/* @noinspection DuplicatedCode */
setlocale(LC_TIME, 'pl_PL.utf8');

$baseDir = dirname(__DIR__);

require_once $baseDir . '/vendor/autoload.php';

if (function_exists('xdebug_set_filter')) {
    /** @noinspection DebugFunctionUsageInspection */
    xdebug_set_filter(XDEBUG_FILTER_STACK, XDEBUG_PATH_EXCLUDE, [ $baseDir . '/vendor/' ]);
}

if (class_exists(KinkRichRenderer::class)) {
    KinkRichRenderer::$theme = 'aante-light.css';
}

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
