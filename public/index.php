<?php
ini_set('display_errors', 'on');
error_reporting(E_ALL);

define('BASE_DIR', realpath(__DIR__ . '/..'));

require_once BASE_DIR . '/vendor/autoload.php';

$app = new \Slim\Slim();
$app->setName('assistant');

// boostrap app
require_once BASE_DIR . '/app/bootstrap.inc';

// start app
$app->run();
