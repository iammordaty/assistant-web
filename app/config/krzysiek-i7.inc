<?php

/* @var $app Slim */

/*
 * Konfiguracja "krzysiek-i7"
 */

use Slim\Slim;

$app->configureMode(
    'krzysiek-i7',
    function () use ($app) {

        require_once BASE_DIR . '/app/config/default.inc';

        $app->config('debug', true);
    }
);
