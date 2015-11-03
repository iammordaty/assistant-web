<?php

use Symfony\Component\Console;

define('BASE_DIR', realpath(__DIR__ . '/..'));

require_once BASE_DIR . '/vendor/autoload.php';

// prepare app
\Slim\Environment::mock(
    [
        'REQUEST_METHOD' => 'CLI',
        'REQUEST_URI' => '',
        'PATH_INFO' => ''
    ]
);

$app = new \Slim\Slim();
$app->setName('assistant-console');
$app->container['mode'] = gethostname();

// add additional configuration
require_once BASE_DIR . '/app/config/' . gethostname() . '.inc';

// prepare tasks and console app
$console = new Console\Application();
$console->addCommands(
    [
        new \Assistant\Module\Collection\Task\CleanerTask($app),
        new \Assistant\Module\Collection\Task\IndexerTask($app),
    ]
);

$console->run();
