<?php

use DI\ContainerBuilder;
use Symfony\Component\Console\Application;

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

set_error_handler(function (int $type, string $msg, string $file, int $line) {
    if (!(error_reporting() & $type)) {
        return;
    }

    // raise warnings to exceptions, so they will be handled by symfony/console
    throw new ErrorException($msg, 0, $type, $file, $line);
});

$console = new Application();
$console->addCommands((require_once $baseDir . '/config/tasks.inc')($container));

unset($baseDir, $container);

/** @noinspection PhpUnhandledExceptionInspection */
$console->run();
