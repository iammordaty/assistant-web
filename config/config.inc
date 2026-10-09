<?php

use Assistant\Module\Common\Extension\BeatportApiClient;
use Assistant\Module\Common\Extension\TrackSearch\BeatportSearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\GoogleBeatportSearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\GoogleSearchApiClient;
use Assistant\Module\Track\Extension\BeatportTrackMetadataSuggestionsService;
use Assistant\Module\Track\Extension\Similarity\RelatedTracksMode;
use Assistant\Module\Track\Extension\Similarity\Similarity;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Favorite;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\IndexedPeriod;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Publisher;
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
            'ready_dir' => '/collection/_new/_zrobione',
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
                    'Eli & Fur',
                    'Ernesto vs. Bastian',
                    'Gabriel & Castellon',
                    'Gabriel & Dresden',
                    'Hercules & Love Affair',
                    'Jordan & Baker',
                    'Jos & Eli',
                    'Leftwing & Kody',
                    'Mr. Belt & Wezol',
                    'Norwood & Hills',
                    'OC & Verde',
                    'Pe & Dan',
                    'Pep & Rash',
                    'Pig & Dan',
                    'Pitchy & Scratchy',
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
                Genre::NAME => [ 'min_similarity' => 0, 'max_similarity' => 100 ],
                IndexedPeriod::NAME => [ 'tolerance' => 2, 'max_tolerance' => 12, 'min_tracks' => 30 ],
                Year::NAME => [ 'tolerance' => 5 ],
            ],
            'providers_weights' => [
                Bpm::NAME => 0.70,
                // ulubione są wyłącznie filtrem i nie dają wyniku, ale kalkulator wymaga wagi każdego dostawcy
                Favorite::NAME => 0,
                Genre::NAME => 0.75,
                IndexedPeriod::NAME => 0.50,
                MusicalKey::NAME => 0.90,
                Musly::NAME => 1,
                Publisher::NAME => 0.40,
                Year::NAME => 0.60,
            ],
            'min_similarity_value' => 60,
            'max_tracks' => 200,

            // Presety listy powiązanych utworów. Klucze `providers_weights` to dostawcy zaznaczani przez preset,
            // a ich wagi oraz `providers_parameters` są nakładane na podstawowe. `track_offsets` przesuwa cel
            // wpisywany do formularza: `bpm_percent` (tempo w procentach), `bpm_pulse` (half-time / double-time),
            // `musical_key` (krok w kole Camelota), `musical_key_scale` ('A', 'B' lub 'toggle'). Kandydaci Musly
            // omijają kryteria metadanych, dlatego presety przesuwające cel wyłączają ich (`musly_candidates`).
            // „Podobne” nie ma wpisu: używa dostawców domyślnych i parametrów podstawowych.
            'modes' => [
                RelatedTracksMode::LIFT->value => [
                    'providers_weights' => [ Bpm::NAME => 0.90, MusicalKey::NAME => 0.90, Musly::NAME => 0.50 ],
                    'providers_parameters' => [ Bpm::NAME => [ 'tolerance' => 1 ] ],
                    'track_offsets' => [ 'bpm_percent' => 2.5, 'musical_key' => 1, 'musical_key_scale' => 'B' ],
                    'musly_candidates' => false,
                ],
                RelatedTracksMode::ENERGY_BOOST->value => [
                    'providers_weights' => [ Bpm::NAME => 0.90, MusicalKey::NAME => 1, Musly::NAME => 0.30 ],
                    'providers_parameters' => [ Bpm::NAME => [ 'tolerance' => 2 ] ],
                    'track_offsets' => [ 'bpm_percent' => 4, 'musical_key' => 2 ],
                    'musly_candidates' => false,
                ],
                RelatedTracksMode::COOL_DOWN->value => [
                    'providers_weights' => [ Bpm::NAME => 0.90, MusicalKey::NAME => 0.90, Musly::NAME => 0.50 ],
                    'providers_parameters' => [ Bpm::NAME => [ 'tolerance' => 1 ] ],
                    'track_offsets' => [ 'bpm_percent' => -2.5, 'musical_key' => -1, 'musical_key_scale' => 'A' ],
                    'musly_candidates' => false,
                ],
                RelatedTracksMode::MOOD_SWITCH->value => [
                    'providers_weights' => [ Bpm::NAME => 0.90, IndexedPeriod::NAME => 0.50, MusicalKey::NAME => 0.90, Musly::NAME => 0.30 ],
                    'providers_parameters' => [ Bpm::NAME => [ 'tolerance' => 2 ] ],
                    'track_offsets' => [ 'musical_key_scale' => 'toggle' ],
                    'musly_candidates' => false,
                ],
                RelatedTracksMode::STYLE_SWITCH->value => [
                    'providers_weights' => [ Bpm::NAME => 1, Genre::NAME => 0.50, IndexedPeriod::NAME => 0.50, MusicalKey::NAME => 1, Musly::NAME => 0.30 ],
                    'providers_parameters' => [
                        Bpm::NAME => [ 'tolerance' => 2 ],
                        Genre::NAME => [ 'min_similarity' => 60, 'max_similarity' => 80 ],
                    ],
                    'musly_candidates' => false,
                ],
                RelatedTracksMode::PULSE_SWITCH->value => [
                    'providers_weights' => [ Bpm::NAME => 1, MusicalKey::NAME => 0.90 ],
                    'providers_parameters' => [ Bpm::NAME => [ 'tolerance' => 2 ] ],
                    'track_offsets' => [ 'bpm_pulse' => true ],
                ],
                RelatedTracksMode::RESET->value => [
                    'providers_weights' => [ Bpm::NAME => 1 ],
                    'providers_parameters' => [ Bpm::NAME => [ 'tolerance' => 3 ] ],
                ],
            ],
        ],

        BeatportApiClient::class => [
            'api_url' => $_ENV['BEATPORT_API_URL'],
            'client_id' => $_ENV['BEATPORT_API_CLIENT_ID'],
            'client_secret' => $_ENV['BEATPORT_API_CLIENT_SECRET'] ?? null,
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
