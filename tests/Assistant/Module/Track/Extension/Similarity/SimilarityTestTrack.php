<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Model\Track;
use DateTime;

/** Tworzy utwór do testów modułu podobieństwa, z podanymi wartościami istotnymi dla dostawców */
final class SimilarityTestTrack
{
    public static function create(
        ?float $bpm = 128.0,
        ?string $initialKey = '8A',
        ?string $genre = 'House',
        ?DateTime $indexedDate = null,
    ): Track {
        return new Track(
            id: null,
            guid: 'artist-track',
            artist: 'Artist',
            artists: [ 'Artist' ],
            title: 'Track',
            album: null,
            trackNumber: null,
            year: 2020,
            genre: $genre,
            publisher: null,
            bpm: $bpm,
            initialKey: $initialKey,
            length: 300,
            tags: [],
            isFavorite: false,
            metadataMd5: 'md5',
            parent: '/collection/Singles/2026/04. kwiecień',
            pathname: '/collection/Singles/2026/04. kwiecień/Artist - Track.mp3',
            modifiedDate: new DateTime(),
            indexedDate: $indexedDate ?? new DateTime('2026-04-15'),
        );
    }
}
