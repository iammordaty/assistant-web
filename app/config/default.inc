<?php

use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\CamelotKeyCode;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Slim\Slim;

/* @var $app Slim */

$app->config([
    'debug' => true,
    'log.enabled' => true,
    'base_dir' => BASE_DIR,
]);

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
        'indexed_dirs' => [ ],
        'incoming_dir' => '',
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
                        'SHDW & Obscure Shape',
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
                'enabled' => [
                    Bpm::class,
                    CamelotKeyCode::class,
                    Genre::class,
                    Musly::class,
                    Year::class,
                ],
                'parameters' => [
                    Bpm::class => [
                        'tolerance' => 5,
                    ],
                    CamelotKeyCode::class => [
                    ],
                    Genre::class => [
                    ],
                    Musly::class => [
                    ],
                    Year::class => [
                        'tolerance' => 2,
                    ],
                ],
                'weights' => [
                    Bpm::class => 0.70,
                    CamelotKeyCode::class => 0.90,
                    Genre::class => 0.75,
                    Musly::class => 1,
                    Year::class => 0.60,
                ],
            ],
            'limit' => [
                'tracks' => 200,
                'value' => 60,
            ],
        ],
    ]
];

