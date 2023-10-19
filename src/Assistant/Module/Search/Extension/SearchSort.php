<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Storage\Storage;

final class SearchSort
{
    public const ARTIST = 'a';
    public const ARTIST_DESCENDING = 'ad';
    public const NEWEST = 'yd';
    public const OLDEST = 'y';
    public const RECENTLY_INDEXED = 'id';
    public const LATEST_INDEXED = 'i';
    public const TEXT_SCORE = 't';

    public static function create(?string $sort, string $default): array
    {
        $sort = $sort ?: $default;

        $sort = match ($sort) {
            self::ARTIST => [ 'guid' => Storage::SORT_ASC ],
            self::ARTIST_DESCENDING => [ 'guid' => Storage::SORT_DESC ],

            self::NEWEST => [ 'year' => Storage::SORT_DESC ],
            self::OLDEST => [ 'year' => Storage::SORT_ASC ],

            self::RECENTLY_INDEXED => [ 'indexed_date' => Storage::SORT_DESC ],
            self::LATEST_INDEXED => [ 'indexed_date' => Storage::SORT_ASC ],

            self::TEXT_SCORE => Storage::SORT_TEXT_SCORE_DESC,
        };

        return $sort;
    }
}
