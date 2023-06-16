<?php

use Assistant\Module\Track\Extension\Similarity\Similarity;
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
            'metadata_dirs' => [
                'music_similarity' => '/metadata/musly',
                'music_classifier' => '/metadata/essentia',
            ],
        ],

        'track_metadata_parser' => [
            'artist' => [
                'delimiters' => [
                    ' & ',
                    ' + ',
                    ' X ',
                    ' and ',
                    ' feat. ',
                    ' meets ',
                    ' pres. ',
                    ' present ',
                    ' presents ',
                    ' vs. ',
                    ' with ',
                    ' x ',
                    ',',
                ],
                'exceptions' => [
                    'Block & Crown',
                    'Cause & Affect',
                    'Ernesto vs. Bastian',
                    'Gabriel & Castellon',
                    'Gabriel & Dresden',
                    'Hercules & Love Affair',
                    'Jordan & Baker',
                    'Leftwing & Kody',
                    'Mr. Belt & Wezol',
                    'Norwood & Hills',
                    'OC & Verde',
                    'Pe & Dan',
                    'Pep & Rash',
                    'Pig & Dan',
                    'Prok & Fitch',
                    'SHDW & Obscure Shape',
                    'Sophie and The Giants',
                    'Spencer & Hill',
                    'Tube & Berger',
                    'Woolfy vs. Projections',
                    'Years & Years',
                ]
            ],

            'title' => [

            ],
        ],

        Similarity::class => [
            'providers_parameters' => [
                Bpm::NAME => [ 'tolerance' => 5 ],
                Year::NAME => [ 'tolerance' => 3 ],
            ],
            'providers_weights' => [
                Bpm::NAME => 0.70,
                Genre::NAME => 0.75,
                MusicalKey::NAME => 0.90,
                Musly::NAME => 1,
                Year::NAME => 0.60,
            ],
            'min_similarity_value' => 60,
            'max_tracks' => 200,
        ],
    ];

    return $config;
};
