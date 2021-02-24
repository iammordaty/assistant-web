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

        $parameters = $app->container['parameters'];

        $collectionRootDir = $parameters['collection']['root_dir'];

        $parameters['collection'] = array_merge($parameters['collection'], [
            'indexed_dirs' => [
                $collectionRootDir . '/Singles',
                $collectionRootDir . '/Other',
            ],
            'incoming_dir' => $collectionRootDir . '/_new',
        ]);

        $app->container['parameters'] = $parameters;
    }
);
