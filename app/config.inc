<?php

use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Dotenv\Dotenv;

return static function (string $baseDir): array {
    (Dotenv::createImmutable($baseDir))->load();

    $config = [
        'base_dir' => $baseDir,

        'storage' => [
            'connection_uri' => 'mongodb://assistant-mongo',
            'host' => 'assistant-mongo/assistant',
            'database' => 'assistant',
            'options' => [ ]
        ],

        'collection' => [
            'root_dir' => '/collection',
            'indexed_dirs' => [
                '/collection/Singles',
                '/collection/Other',
            ],
            'incoming_dir' => '/collection/_new',
        ],

        'track_metadata_parser' => [
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
        ],

        'similarity' => [
            'providers' => [
                'enabled' => [
                    Bpm::class,
                    Genre::class,
                    MusicalKey::class,
                    Musly::class,
                    Year::class,
                ],
                'parameters' => [
                    Bpm::class => [
                        'tolerance' => 5,
                    ],
                    Genre::class => [
                    ],
                    MusicalKey::class => [
                    ],
                    Musly::class => [
                    ],
                    Year::class => [
                        'tolerance' => 2,
                    ],
                ],
                'weights' => [
                    Bpm::class => 0.70,
                    Genre::class => 0.75,
                    MusicalKey::class => 0.90,
                    Musly::class => 1,
                    Year::class => 0.60,
                ],
            ],
            'limit' => [
                'tracks' => 200,
                'value' => 60,
            ],
        ],
    ];

    return $config;
};
