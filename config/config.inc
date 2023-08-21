<?php

use Assistant\Module\Common\Extension\BeatportApiClient;
use Assistant\Module\Common\Extension\TrackSearch\BeatportSearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\GoogleBeatportSearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\GoogleSearchApiClient;
use Assistant\Module\Track\Extension\BeatportTrackMetadataSuggestionsService;
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

        BeatportApiClient::class => [
            'api_url' => $_ENV['BEATPORT_API_URL'],
            'client_id' => $_ENV['BEATPORT_API_CLIENT_ID'],
            'client_secret' => $_ENV['BEATPORT_API_CLIENT_SECRET'],
            'username' => $_ENV['BEATPORT_API_USERNAME'],
            'password' => $_ENV['BEATPORT_API_PASSWORD'],
        ],

        BeatportTrackMetadataSuggestionsService::class => [
            BeatportSearchTracks::class => [
                'enabled' => filter_var($_ENV['USE_BEATPORT_SEARCH'], FILTER_VALIDATE_BOOLEAN),
            ],
            GoogleSearchApiClient::class => [
                'api_key' => $_ENV['GOOGLE_SEARCH_API_KEY'],
                'search_id' => $_ENV['GOOGLE_SEARCH_API_SEARCH_ID'],
            ],
            GoogleBeatportSearchTracks::class => [
                'enabled' => filter_var($_ENV['USE_GOOGLE_SEARCH'], FILTER_VALIDATE_BOOLEAN),
            ],
        ],
    ];

    return $config;
};
