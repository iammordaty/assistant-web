<?php

use DI\ContainerBuilder;
use Symfony\Component\Console\Application;

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

set_error_handler(function (int $type, string $msg, string $file, int $line) {
    if (!(error_reporting() & $type)) {
        return;
    }

    // raise warnings to exceptions, so they will be handled by symfony/console
    throw new ErrorException($msg, 0, $type, $file, $line);
});

$console = new Application();
$console->addCommands((require_once BASE_DIR . '/config/tasks.inc')($container));

unset($container);

/** @noinspection PhpUnhandledExceptionInspection */
$console->run();
