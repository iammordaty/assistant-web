<?php

/** @noinspection PhpIncludeInspection */

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(BASE_DIR);
$dotenv->load();

// require environment config
require_once sprintf('%s/app/config/%s.inc', BASE_DIR, getenv('SLIM_MODE'));

// require routes
require BASE_DIR . '/src/Assistant/Module/Common/Resources/routes/default.inc';
require BASE_DIR . '/src/Assistant/Module/Dashboard/Resources/routes/default.inc';
require BASE_DIR . '/src/Assistant/Module/Directory/Resources/routes/default.inc';
require BASE_DIR . '/src/Assistant/Module/Mix/Resources/routes/default.inc';
require BASE_DIR . '/src/Assistant/Module/Search/Resources/routes/default.inc';
require BASE_DIR . '/src/Assistant/Module/Track/Resources/routes/default.inc';

// require services
require_once sprintf('%s/app/services.inc', BASE_DIR);

