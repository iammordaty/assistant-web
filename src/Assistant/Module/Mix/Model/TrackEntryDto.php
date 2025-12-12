<?php

namespace Assistant\Module\Mix\Model;

use Assistant\Module\Track\Model\Track;
use MongoDB\Model\BSONDocument;

final class TrackEntryDto
{
    public function __construct(
        public string $trackGuid,
        public Track|null $track,
        /** @var CommentDto[] */
        public array $comments,
    ) {}

    public static function fromStorage(BSONDocument $document): self
    {
        $comments = isset($document->comments) ? $document->comments->getArrayCopy() : [];

        return new self(
            $document->track,
            null,
            array_map(
                fn ($comment) => CommentDto::fromStorage($comment),
                $comments
            )
        );
    }

    public static function fromModel(TrackEntry $entry): self
    {
        return new self(
            $entry->trackGuid,
            $entry->track,
            array_map(
                fn (Comment $comment) => CommentDto::fromModel($comment),
                $entry->comments
            )
        );
    }

    public static function fromJson(array $entry): self
    {
        $comments = array_map(
            fn (array $comment) => CommentDto::fromJson($comment),
            $entry['comments'] ?? []
        );

        return new self(
            trackGuid: $entry['trackGuid'],
            track: null,
            comments: $comments,
        );
    }

    public function toStorage(): array
    {
        return [
            'track' => $this->trackGuid,
            'comments' => array_map(
                fn (CommentDto $dto) => $dto->toStorage(),
                $this->comments,
            ),
        ];
    }

    public function toJson(): array
    {
        $trackToJson = fn (Track $track): array => [
            'guid' => $track->getGuid(),
            'artist' => $track->getArtist(),
            'artists' => $track->getArtists(),
            'title' => $track->getTitle(),
            'name' => $track->getName(),
            'album' => $track->getAlbum(),
            'trackNumber' => $track->getTrackNumber(),
            'year' => $track->getYear(),
            'genre' => $track->genre,
            'publisher' => $track->getPublisher(),
            'bpm' => $track->bpm,
            'initialKey' => $track->initialKey,
            'length' => $track->length,
            'tags' => $track->getTags(),
            'isFavorite' => $track->getIsFavorite(),
            'metadataMd5' => $track->getMetadataMd5(),
            'parent' => $track->getParent(),
            'pathname' => $track->getPathname(),
        ];

        return [
            'trackGuid' => $this->trackGuid,
            'track' => $this->track ? $trackToJson($this->track) : null,
            'comments' => array_map(
                fn (CommentDto $dto) => $dto->toJson(),
                $this->comments,
            ),
        ];
    }
}
