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

        $servers = $app->container->servers;
        $servers['db']['host'] = 'assistant-mongo/assistant';
        $app->container->servers = $servers;

        $parameters = [
            'collection' => [
                'indexer' => [
                    'excluded_dirs' => [
                        '/_new',
                        '/Albums',
                        '/Tools',
                        '/Sets',
                        '/Other/- 2006',
                        '/Other/2006'
                    ]
                ]
            ]
        ];

        $app->container->parameters = array_merge_recursive(
            $app->container->parameters,
            $parameters
        );
    }
);
