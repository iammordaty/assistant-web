<?php

namespace Assistant\Module\Search\Extension\Criteria;

final class SearchCriteria
{
    /**
     * @param string|null $name
     * @param SearchCriteriaField|array|string|null $guid
     * @param SearchCriteriaField|string|null $artist
     * @param SearchCriteriaField|string|null $title
     * @param SearchCriteriaField[]|string[]|null $genres
     * @param SearchCriteriaField[]|string[]|null $publishers
     * @param MinMaxInfo|int[]|null $years
     * @param string[]|null $initialKeys
     * @param MinMaxInfo|float[]|null $bpm
     * @param true|null $isFavorite
     * @param MinMaxInfo|\DateTimeInterface[]|null $indexedDates
     * @param SearchCriteriaField|string|null $parent
     * @param SearchCriteriaField[]|string[]|null $pathname
     */
    public function __construct(
        private ?string $name = null,
        private SearchCriteriaField|array|string|null $guid = null,
        private SearchCriteriaField|string|null $artist = null,
        private SearchCriteriaField|string|null $title = null,
        private ?array $genres = null,
        private ?array $publishers = null,
        private MinMaxInfo|array|null $years = null,
        private ?array $initialKeys = null,
        private MinMaxInfo|array|null $bpm = null,
        private true|null $isFavorite = null,
        private MinMaxInfo|array|null $indexedDates = null,
        private SearchCriteriaField|string|null $parent = null,
        private ?array $pathname = null,
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return SearchCriteriaField|array|string|null */
    public function getGuid(): SearchCriteriaField|array|string|null
    {
        return $this->guid;
    }

    public function getArtist(): SearchCriteriaField|string|null
    {
        return $this->artist;
    }

    public function getTitle(): SearchCriteriaField|string|null
    {
        return $this->title;
    }

    /** @return SearchCriteriaField[]|string[]|null */
    public function getGenres(): ?array
    {
        return $this->genres;
    }

    /** @return SearchCriteriaField[]|string[]|null */
    public function getPublishers(): ?array
    {
        return $this->publishers;
    }

    /** @return MinMaxInfo|int[]|null */
    public function getYears(): MinMaxInfo|array|null
    {
        return $this->years;
    }

    /** @return string[]|null */
    public function getInitialKeys(): ?array
    {
        return $this->initialKeys;
    }

    /** @return MinMaxInfo|float[]|null */
    public function getBpm(): MinMaxInfo|array|null
    {
        return $this->bpm;
    }

    public function getIsFavorite(): true|null
    {
        return $this->isFavorite;
    }

    /** @return MinMaxInfo|\DateTimeInterface[]|null */
    public function getIndexedDates(): MinMaxInfo|array|null
    {
        return $this->indexedDates;
    }

    public function getParent(): SearchCriteriaField|string|null
    {
        return $this->parent;
    }

    /** @return SearchCriteriaField[]|string[]|null */
    public function getPathname(): ?array
    {
        return $this->pathname;
    }
}
