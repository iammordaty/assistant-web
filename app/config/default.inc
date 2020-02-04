<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use MongoDB\Client as MongoDBClient;
use Slim\Slim;

/* @var $app Slim */

$app->config(
    [
        'debug' => true,
        'log.enabled' => true,
        'base_dir' => BASE_DIR,
    ]
);

$app->container->servers = [
    'db' => [
        'connection_uri' => 'mongodb://assistant-mongo',
        'host' => 'assistant-mongo/assistant',
        'database' => 'assistant',
        'options' => [ ]
    ],
];

$app->container->parameters = [
    'collection' => [
        'root_dir' => '/collection',
        'ignored_dirs' => [ ],
        'excluded_dirs' => [ ]
    ],

    'track' => [
        'metadata' => [
            'parser' => [
                'artist' => [
                    'delimiters' => [
                        ' & ',
                        ' + ',
                        ',',
                        ' and ',
                        ' feat. ',
                        ' meets ',
                        ' vs. ',
                        ' with ',
                        ' x ',
                        ' X ',
                    ],
                    'exceptions' => [
                        'Cause & Affect',
                        'Gabriel & Castellon',
                        'Hercules & Love Affair',
                        'Leftwing & Kody',
                        'Mr. Belt & Wezol',
                        'Pep & Rash',
                        'Pe & Dan',
                        'Pig & Dan',
                        'Prok & Fitch',
                        'Tube & Berger',
                        'Years & Years',
                    ]
                ],

                'title' => [

                ],
            ]
        ],

        'similarity' => [
            'providers' => [
                'names' => [
                    'bpm',
                    'camelotKeyCode',
                    'genre',
                    'musly',
                    'year',
                ],
                'parameters' => [
                    'bpm' => [
                        'tolerance' => 5,
                        'weight' => 0.70,
                    ],
                    'camelotKeyCode' => [
                        'weight' => 0.90,
                    ],
                    'genre' => [
                        'weight' => 0.75,
                    ],
                    'musly' => [
                        'weight' => 1,
                    ],
                    'year' => [
                        'tolerance' => 2,
                        'weight' => 0.60,
                    ],
                ]
            ],
            'limit' => [
                'tracks' => 200,
                'value' => 60,
            ],
        ],
    ]
];

$app->container->singleton(
    'db',
    function () use ($app) {
        [ 'connection_uri' => $connectionUri, 'database' => $database ] = $app->container->servers['db'];

        $client = new MongoDBClient($connectionUri);
        $database = $client->selectDatabase($database);

        return $database;
    }
);

$app->container->singleton(
    'log',
    function () use ($app) {
        $logsDir = sprintf('%s/app/logs', $app->config('base_dir'));

        $logger = new Logger(sprintf('%s-logger', $app->getName()));

        $formatter = new LineFormatter('[%datetime%] %level_name% %message% %context% %extra%' . PHP_EOL);
        $formatter->ignoreEmptyContextAndExtra(true);

        $logger->setHandlers(
            [
                (new StreamHandler(sprintf('%s/app.error.log', $logsDir), Logger::ERROR))->setFormatter($formatter),
                (new StreamHandler(sprintf('%s/app.debug.log', $logsDir), Logger::DEBUG))->setFormatter($formatter),
            ]
        );

        return $logger;
    }
);
