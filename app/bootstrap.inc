<?php

/** @noinspection PhpIncludeInspection */

use Dotenv\Dotenv;
use Slim\Slim;

/* @var $app Slim */

$dotenv = Dotenv::createImmutable(BASE_DIR);
$dotenv->load();

// require environment config
require_once sprintf('%s/app/services.inc', BASE_DIR);

// require environment config
require_once sprintf('%s/app/config/%s.inc', BASE_DIR, getenv('SLIM_MODE'));

// require routes
require_once sprintf('%s/app/routes.inc', BASE_DIR);