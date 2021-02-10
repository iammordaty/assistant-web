<?php

use Assistant\Module\Collection\Task\CleanerTask;
use Assistant\Module\Collection\Task\IndexerTask;
use Assistant\Module\Collection\Task\MonitorTask;
use Assistant\Module\Collection\Task\MoverTask;
use Assistant\Module\Collection\Task\ReindexerTask;
use Assistant\Module\Track\Task\AudioDataCalculatorTask;
use Slim\Environment;
use Slim\Slim;
use Symfony\Component\Console;

setlocale(LC_TIME, 'pl_PL.utf8');

define('BASE_DIR', dirname(__DIR__));

require_once BASE_DIR . '/vendor/autoload.php';

// prepare app

Environment::mock([
    'REQUEST_METHOD' => 'CLI',
    'REQUEST_URI' => '',
    'PATH_INFO' => ''
]);

$app = new Slim();
$app->setName('assistant-console');

// add additional configuration
require_once sprintf('%s/app/config/%s.inc', BASE_DIR, getenv('SLIM_MODE'));

// prepare tasks and console app
$console = new Console\Application();
$console->addCommands([
    new CleanerTask($app),
    new IndexerTask($app),
    new MoverTask($app),
    new ReindexerTask($app),
    new AudioDataCalculatorTask($app),
    new MonitorTask($app),
]);

/** @noinspection PhpUnhandledExceptionInspection */
$console->run();
