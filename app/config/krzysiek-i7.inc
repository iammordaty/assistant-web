<?php

/* @var $app \Slim\Slim */

/*
 * Konfiguracja "krzysiek-i7"
 */
$app->configureMode(
    'krzysiek-i7',
    function () use ($app) {

        require_once BASE_DIR . '/app/config/default.inc';

        $app->config('debug', true);
    }
);
