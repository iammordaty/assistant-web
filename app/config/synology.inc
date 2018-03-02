<?php

/* @var $app \Slim\Slim */

/*
 * Konfiguracja synology
 */
$app->configureMode(
    'synology',
    function () use ($app) {

        require_once BASE_DIR . '/app/config/default.inc';

        $app->config('debug', true);

        $parameters = [
            'collection' => [
                'excluded_dirs' => [
                    '/_new',
                    '/Albums',
                    '/Tools',
                    '/Sets',
                    '@eaDir',
                ]
            ]
        ];

        $app->container->parameters = array_merge_recursive(
            $app->container->parameters,
            $parameters
        );
    }
);
