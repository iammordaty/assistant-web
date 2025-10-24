<?php

namespace Assistant\Module\Track\Extension;

final readonly class TrackMetadataFields
{
    public const ALBUM = 'album';
    public const ARTIST = 'artist';
    public const BPM = 'bpm';
    public const GENRE = 'genre';
    public const INITIAL_KEY = 'initial_key';
    public const PUBLISHER = 'publisher';
    public const TITLE = 'title';
    public const TRACK_NUMBER = 'track_number';
    public const YEAR = 'year';

    public static function isSupportedMetadataField(string $field): bool
    {
        return in_array($field, self::supported());
    }

    public static function supported(): array
    {
        return [
            self::ALBUM,
            self::ARTIST,
            self::BPM,
            self::GENRE,
            self::INITIAL_KEY,
            self::PUBLISHER,
            self::TITLE,
            self::TRACK_NUMBER,
            self::YEAR,
        ];
    }
}
