<?php

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Symfony\Component\Console\Application;

setlocale(LC_TIME, 'pl_PL.utf8');

define('BASE_DIR', dirname(__DIR__));

require_once BASE_DIR . '/vendor/autoload.php';

// require config and set up dependencies

$config = (require_once BASE_DIR . '/app/config.inc')(BASE_DIR);

/** @noinspection PhpUnhandledExceptionInspection */
$container = (new ContainerBuilder())
    ->addDefinitions((require_once BASE_DIR . '/app/container.inc')(BASE_DIR, $config))
    ->build();

$app = Bridge::create($container);

(require_once BASE_DIR . '/app/middleware.inc')($app);

// prepare tasks and console app

$console = new Application();
$console->addCommands((require_once BASE_DIR . '/app/tasks.inc')($container));

unset($app, $container);

/** @noinspection PhpUnhandledExceptionInspection */
$console->run();
