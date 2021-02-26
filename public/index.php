<?php

use Slim\Slim;

define('BASE_DIR', dirname(__DIR__));

require_once BASE_DIR . '/vendor/autoload.php';

$app = new Slim();
$app->setName('assistant');

// bootstrap app
require_once BASE_DIR . '/app/bootstrap.inc';

// require routes
require_once BASE_DIR . '/app/routes.inc';

// start app
$app->run();
