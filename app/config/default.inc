<?php

/* @var $app \Slim\Slim */

$app->config(
    [
        'debug' => true,
        'log.enabled' => true,
        'base_dir' => BASE_DIR,
    ]
);

$app->container->servers = [
    'db' => [
        'host' => 'assistant-mongo/assistant',
        'database' => 'assistant',
        'options' => [ ]
    ],
];

$app->container->parameters = [
    'collection' => [
        'indexer' => [
            'root_dir' => '/collection',
            'ignored_dirs' => [ ],
            'excluded_dirs' => [ ]
        ]
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
            'limit' => [
                'tracks' => 200,
                'value' => 60,
            ],
            'weights' => [
                'camelotKeyCode' => 1,
                'genre' => 0.90,
                'bpm' => 0.80,
                'year' => 0.70,
            ],
            'provider' => [
                'bpm' => [
                    'tolerance' => 5,
                ],
                'year' => [
                    'tolerance' => 2,
                ],
            ]
        ],
    ]
];

$app->container->singleton(
    'db',
    function () use ($app) {
        $client = (new MongoClient($app->container->servers['db']['host'], $app->container->servers['db']['options']))
            ->selectDB($app->container->servers['db']['database']);

        return $client;
    }
);
