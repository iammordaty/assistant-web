<?php

use Symfony\Component\Console;

setlocale(LC_TIME, 'pl_PL.utf8');

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

// add additional configuration
require_once sprintf('%s/app/config/%s.inc', BASE_DIR, getenv('SLIM_MODE'));

// prepare tasks and console app
$console = new Console\Application();
$console->addCommands(
    [
        new \Assistant\Module\Collection\Task\CleanerTask($app),
        new \Assistant\Module\Collection\Task\IndexerTask($app),
        new \Assistant\Module\Collection\Task\MoverTask($app),
        new \Assistant\Module\Collection\Task\ReindexerTask($app),
        new \Assistant\Module\Track\Task\AudioDataCalculatorTask($app),
        new \Assistant\Module\Track\Task\CleanerTask($app),
    ]
);

$console->run();
