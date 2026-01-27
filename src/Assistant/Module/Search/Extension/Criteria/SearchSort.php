<?php

namespace Assistant\Module\Search\Extension\Criteria;

use Assistant\Module\Common\Storage\Storage;

final readonly class SearchSort
{
    private const string NAME_ASC = 'a';
    private const string NAME_DESC = 'ad';
    private const string INDEXED_ASC = 'i';
    private const string INDEXED_DESC = 'id';
    private const string TEXT_SCORE = 't';
    private const string YEAR_ASC = 'y';
    private const string YEAR_DESC = 'yd';

    private function __construct(private array $query, private string $string) {
    }

    public static function byName(): self
    {
        return new self([ 'guid' => Storage::SORT_ASC ], self::NAME_ASC);
    }

    public static function byNameDesc(): self
    {
        return new self([ 'guid' => Storage::SORT_DESC ], self::NAME_DESC);
    }

    public static function byNewest(): self
    {
        return new self([ 'year' => Storage::SORT_DESC ], self::YEAR_DESC);
    }

    public static function byOldest(): self
    {
        return new self([ 'year' => Storage::SORT_ASC ], self::YEAR_ASC);
    }

    public static function byMostRecentlyIndexed(): self
    {
        return new self([ 'indexed_date' => Storage::SORT_DESC ], self::INDEXED_DESC);
    }

    public static function byLeastRecentlyIndexed(): self
    {
        return new self([ 'indexed_date' => Storage::SORT_ASC ], self::INDEXED_ASC);
    }

    public static function byTextScore(): self
    {
        return new self(Storage::SORT_TEXT_SCORE_DESC, self::TEXT_SCORE);
    }

    public static function fromQueryString(?string $string, self $default): self
    {
        return match ($string) {
            self::NAME_ASC => self::byName(),
            self::NAME_DESC => self::byNameDesc(),
            self::YEAR_ASC => self::byOldest(),
            self::YEAR_DESC => self::byNewest(),
            self::INDEXED_ASC => self::byLeastRecentlyIndexed(),
            self::INDEXED_DESC => self::byMostRecentlyIndexed(),
            self::TEXT_SCORE => self::byTextScore(),
            default => $default,
        };
    }

    public function toStorage(): array
    {
        return $this->query;
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
