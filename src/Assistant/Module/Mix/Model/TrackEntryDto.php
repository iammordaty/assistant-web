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

    public function toStorage(): array
    {
        return [
            'track' => $this->trackGuid,
            'comments' => array_map(
                fn (CommentDto $dto) => $dto->toStorage(),
                $this->comments
            ),
        ];
    }
}
