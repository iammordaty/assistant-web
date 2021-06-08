<?php

namespace Assistant\Module\Track\Model;

final class TrackMetadataSuggestions
{
    public function __construct(
        private array $artist,
        private array $title,
        private array $album,
        private array $trackNumber,
        private array $year,
        private array $genre,
        private array $publisher,
        private array $bpm,
        private array $initialKey,
        private array $tags
    ) {
    }

    public function toArray(): array
    {
        return [
            'artist' => $this->getArtist(),
            'title' => $this->getTitle(),
            'album' => $this->getAlbum(),
            'trackNumber' => $this->getTrackNumber(),
            'year' => $this->getYear(),
            'genre' => $this->getGenre(),
            'publisher' => $this->getPublisher(),
            'bpm' => $this->getBpm(),
            'initialKey' => $this->getInitialKey(),
            'tags' => $this->getTags(),
        ];
    }

    public function getArtist(): array
    {
        return $this->artist;
    }

    public function getTitle(): array
    {
        return $this->title;
    }

    public function getAlbum(): array
    {
        return $this->album;
    }

    public function getTrackNumber(): array
    {
        return $this->trackNumber;
    }

    public function getYear(): array
    {
        return $this->year;
    }

    public function getGenre(): array
    {
        return $this->genre;
    }

    public function getPublisher(): array
    {
        return $this->publisher;
    }

    public function getBpm(): array
    {
        return $this->bpm;
    }

    public function getInitialKey(): array
    {
        return $this->initialKey;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
