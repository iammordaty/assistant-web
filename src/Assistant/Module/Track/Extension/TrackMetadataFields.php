<?php

namespace Assistant\Module\Track\Extension;

final readonly class TrackMetadataFields
{
    public const string ALBUM = 'album';
    public const string ARTIST = 'artist';
    public const string BPM = 'bpm';
    public const string GENRE = 'genre';
    public const string INITIAL_KEY = 'initial_key';
    public const string PUBLISHER = 'publisher';
    public const string TITLE = 'title';
    public const string TRACK_NUMBER = 'track_number';
    public const string YEAR = 'year';

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
