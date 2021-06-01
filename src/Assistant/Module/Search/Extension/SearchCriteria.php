<?php

namespace Assistant\Module\Search\Extension;

final class SearchCriteria
{
    public function __construct(
        private ?string $name = null,
        private ?string $artist = null,
        private ?string $title = null,
        private ?array $genres = null,
        private ?array $publishers = null,
        private MinMaxInfo|array|null $years = null,
        private ?array $initialKeys = null,
        private MinMaxInfo|array|null $bpm = null,
        private MinMaxInfo|array|null $indexedDates = null,
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getGenres(): ?array
    {
        return $this->genres;
    }

    public function getPublishers(): ?array
    {
        return $this->publishers;
    }

    public function getYears(): MinMaxInfo|array|null
    {
        return $this->years;
    }

    public function getInitialKeys(): ?array
    {
        return $this->initialKeys;
    }

    public function getBpm(): MinMaxInfo|array|null
    {
        return $this->bpm;
    }

    public function getIndexedDates(): MinMaxInfo|array|null
    {
        return $this->indexedDates;
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }
}
