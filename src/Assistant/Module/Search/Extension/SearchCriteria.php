<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Storage\Regex;

final class SearchCriteria
{
    /**
     * @param string|null $name
     * @param Regex|string|null $guid
     * @param Regex|string|null $artist
     * @param Regex|string|null $title
     * @param Regex[]|string[]|null $genres
     * @param Regex[]|string[]|null $publishers
     * @param MinMaxInfo|int[]|null $years
     * @param string[]|null $initialKeys
     * @param MinMaxInfo|float[]|null $bpm
     * @param MinMaxInfo|\DateTimeInterface[]|null $indexedDates
     * @param string|null $pathname
     */
    public function __construct(
        private ?string $name = null,
        private Regex|string|null $guid = null,
        private Regex|string|null $artist = null,
        private Regex|string|null $title = null,
        private ?array $genres = null,
        private ?array $publishers = null,
        private MinMaxInfo|array|null $years = null,
        private ?array $initialKeys = null,
        private MinMaxInfo|array|null $bpm = null,
        private MinMaxInfo|array|null $indexedDates = null,
        private ?string $pathname = null,
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getGuid(): Regex|string|null
    {
        return $this->guid;
    }

    public function getArtist(): Regex|string|null
    {
        return $this->artist;
    }

    public function getTitle(): Regex|string|null
    {
        return $this->title;
    }

    /** @return Regex[]|string[]|null */
    public function getGenres(): ?array
    {
        return $this->genres;
    }

    /** @return Regex[]|string[]|null */
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

    /** @return MinMaxInfo|\DateTimeInterface[]|null */
    public function getIndexedDates(): MinMaxInfo|array|null
    {
        return $this->indexedDates;
    }

    public function getPathname(): ?string
    {
        return $this->pathname;
    }
}
