<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Model\Track;
use DateTime;

/**
 * Buduje utwory na potrzeby testów podobieństwa. Konstruktor Track wymaga dwudziestu argumentów,
 * z których dla podobieństwa znaczenie mają tylko tempo, gatunek, tonacja, rok i ścieżka.
 */
final class TrackFactory
{
    public static function create(
        ?float $bpm = 128.0,
        ?string $genre = 'House',
        ?string $initialKey = '8A',
        ?int $year = 2024,
        string $guid = 'artist-title',
        ?string $pathname = null,
        string $artist = 'Artist',
    ): Track {
        return new Track(
            id: null,
            guid: $guid,
            artist: $artist,
            artists: [ $artist ],
            title: 'Title',
            album: null,
            trackNumber: null,
            year: $year,
            genre: $genre,
            publisher: null,
            bpm: $bpm,
            initialKey: $initialKey,
            length: 300,
            tags: [],
            isFavorite: false,
            metadataMd5: 'metadata-md5',
            parent: 'parent',
            pathname: $pathname ?? sprintf('/collection/Other/%s.mp3', $guid),
            modifiedDate: new DateTime(),
        );
    }
}
