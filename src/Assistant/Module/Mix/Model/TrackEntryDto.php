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
            trackGuid: $entry['track']['guid'],
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
        $trackToJson = function (?Track $track, string $trackGuid): array {
            if (!$track) {
                return [
                    'found' => false,
                    'guid' => $trackGuid,
                    'name' => str_replace('-', ' ', $trackGuid),
                ];
            }

            return [
                'found' => true,
                'guid' => $this->track->getGuid(),
                'artist' => $this->track->getArtist(),
                'artists' => $this->track->getArtists(),
                'title' => $this->track->getTitle(),
                'name' => $this->track->getName(),
                'album' => $this->track->getAlbum(),
                'trackNumber' => $this->track->getTrackNumber(),
                'year' => $this->track->getYear(),
                'genre' => $this->track->getGenre(),
                'publisher' => $this->track->getPublisher(),
                'bpm' => $this->track->getBpm(),
                'initialKey' => $this->track->getInitialKey(),
                'length' => $this->track->getLength(),
                'tags' => $this->track->getTags(),
                'isFavorite' => $this->track->getIsFavorite(),
                'metadataMd5' => $this->track->getMetadataMd5(),
                'parent' => $this->track->getParent(),
                'pathname' => $this->track->getPathname(),
            ];
        };

        return [
            'track' => $trackToJson($this->track, $this->trackGuid),
            'comments' => array_map(
                fn (CommentDto $dto) => $dto->toJson(),
                $this->comments,
            ),
        ];
    }
}
