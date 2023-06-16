<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Storage\Storage;

final class SearchSort
{
    // Tutaj należałoby jeszcze uwzględnić najlepsze dopasowanie,
    // czyli to, po czym domyślnie sortuje wyszukiwarka prosta.
    private const ARTIST = 'a';
    private const ARTIST_DESCENDING = 'ad';
    private const NEWEST = 'yd';
    private const OLDEST = 'y';
    private const RECENTLY_INDEXED = 'id';
    private const LATEST_INDEXED = 'i';

    public static function create(?string $sort): array
    {
        $sort = match ($sort) {
            self::ARTIST_DESCENDING => [ 'guid' => Storage::SORT_DESC ],

            self::NEWEST => [ 'year' => Storage::SORT_DESC ],
            self::OLDEST => [ 'year' => Storage::SORT_ASC ],

            self::RECENTLY_INDEXED => [ 'indexed_date' => Storage::SORT_DESC ],
            self::LATEST_INDEXED => [ 'indexed_date' => Storage::SORT_ASC ],

            default => [ 'guid' => Storage::SORT_ASC ],
        };

        return $sort;
    }
}
