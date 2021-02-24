<?php

/** @noinspection PhpIncludeInspection */

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(BASE_DIR);
$dotenv->load();

// require environment config
require_once sprintf('%s/app/config/%s.inc', BASE_DIR, getenv('SLIM_MODE'));

// require environment config
require_once sprintf('%s/app/services.inc', BASE_DIR);
