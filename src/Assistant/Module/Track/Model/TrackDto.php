<?php

namespace Assistant\Module\Track\Model;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use stdClass;

final class TrackDto
{
    public function __construct(
        private ?ObjectId $objectId,
        private string $guid,
        private string $artist,
        private BSONArray $artists,
        private string $title,
        private ?string $album,
        private ?int $trackNumber,
        private ?int $year,
        private string $genre,
        private ?string $publisher,
        private float $bpm,
        private string $initialKey,
        private int $length,
        private BSONArray $tags,
        private string $metadataMd5,
        private string $parent,
        private string $pathname,
        private UTCDateTime $modifiedDate,
        private ?UTCDateTime $indexedDate,
    ) {
    }

    public static function fromStorage(stdClass $document): self
    {
        $dto = new self(
            $document->_id,
            $document->guid,
            $document->artist,
            $document->artists,
            $document->title,
            $document->album ?? null,
            $document->track_number ?? null,
            $document->year ?? null,
            $document->genre,
            $document->publisher ?? null,
            $document->bpm,
            $document->initial_key,
            $document->length,
            $document->tags,
            $document->metadata_md5,
            $document->parent,
            $document->pathname,
            $document->modified_date,
            $document->indexed_date,
        );

        return $dto;
    }

    public static function fromModel(Track $track): self
    {
        $modifiedTimestamp = (int) $track->getModifiedDate()->format('U') * 1000;
        $indexedTimestamp = $track->getIndexedDate()
            ? (int) $track->getIndexedDate()->format('U') * 1000
            : null;

        $dto = new self(
            $track->getId() ? new ObjectId($track->getId()) : null,
            $track->getGuid(),
            $track->getArtist(),
            new BSONArray($track->getArtists()),
            $track->getTitle(),
            $track->getAlbum(),
            $track->getTrackNumber(),
            $track->getYear(),
            $track->getGenre(),
            $track->getPublisher(),
            $track->getBpm(),
            $track->getInitialKey(),
            $track->getLength(),
            new BSONArray($track->getTags()),
            $track->getMetadataMd5(),
            $track->getParent(),
            $track->getPathname(),
            new UTCDateTime($modifiedTimestamp),
            $indexedTimestamp ? new UTCDateTime($indexedTimestamp) : null,
        );

        return $dto;
    }

    public function toStorage(): array
    {
        return [
            '_id' => $this->objectId ?: new ObjectId(),
            'guid' => $this->guid,
            'artist' => $this->artist,
            'artists' => $this->artists,
            'title' => $this->title,
            'album' => $this->album,
            'track_number' => $this->trackNumber,
            'year' => $this->year,
            'genre' => $this->genre,
            'publisher' => $this->publisher,
            'bpm' => $this->bpm,
            'initial_key' => $this->initialKey,
            'length' => $this->length,
            'tags' => $this->tags,
            'metadata_md5' => $this->metadataMd5,
            'parent' => $this->parent,
            'pathname' => $this->pathname,
            'modified_date' => $this->modifiedDate,
            'indexed_date' => $this->indexedDate,
        ];
    }

    public function getObjectId(): ?ObjectId
    {
        return $this->objectId;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getArtist(): string
    {
        return $this->artist;
    }

    public function getArtists(): BSONArray
    {
        return $this->artists;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAlbum(): ?string
    {
        return $this->album;
    }

    public function getTrackNumber(): ?int
    {
        return $this->trackNumber;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function getGenre(): string
    {
        return $this->genre;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function getBpm(): float
    {
        return $this->bpm;
    }

    public function getInitialKey(): string
    {
        return $this->initialKey;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getMetadataMd5(): string
    {
        return $this->metadataMd5;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function getPathname(): string
    {
        return $this->pathname;
    }

    public function getModifiedDate(): UTCDateTime
    {
        return $this->modifiedDate;
    }

    public function getIndexedDate(): ?UTCDateTime
    {
        return $this->indexedDate;
    }

    public function getTags(): BSONArray
    {
        return $this->tags;
    }
}
