<?php

namespace Assistant\Module\Track\Model;

final class TrackMetadataSuggestions
{
    private array $artist;
    private array $title;
    private array $album;
    private array $trackNumber;
    private array $year;
    private array $genre;
    private array $publisher;
    private array $bpm;
    private array $initialKey;
    private array $tags;

    public function __construct(
        array $artist,
        array $title,
        array $album,
        array $trackNumber,
        array $year,
        array $genre,
        array $publisher,
        array $bpm,
        array $initialKey,
        array $tags
    ) {
        $this->artist = $artist;
        $this->title = $title;
        $this->album = $album;
        $this->trackNumber = $trackNumber;
        $this->year = $year;
        $this->genre = $genre;
        $this->publisher = $publisher;
        $this->bpm = $bpm;
        $this->initialKey = $initialKey;
        $this->tags = $tags;
    }

    public function toArray(): array
    {
        return [
            'artist' => $this->getArtist(),
            'title' => $this->getTitle(),
            'album' => $this->getAlbum(),
            'track_number' => $this->getTrackNumber(),
            'year' => $this->getYear(),
            'genre' => $this->getGenre(),
            'publisher' => $this->getPublisher(),
            'bpm' => $this->getBpm(),
            'initial_key' => $this->getInitialKey(),
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