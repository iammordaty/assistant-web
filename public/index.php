<?php

ini_set('display_errors', 'on');

error_reporting(E_ALL ^ E_DEPRECATED);

setlocale(LC_TIME, 'pl_PL.utf8');

define('BASE_DIR', dirname(__DIR__) . '');

require_once BASE_DIR . '/vendor/autoload.php';

$app = new \Slim\Slim();
$app->setName('assistant');

// bootstrap app
require_once BASE_DIR . '/app/bootstrap.inc';

// start app
$app->run();
