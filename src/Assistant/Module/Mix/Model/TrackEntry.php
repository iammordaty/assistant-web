<?php

namespace Assistant\Module\Mix\Model;

use Assistant\Module\Track\Model\Track;

final class TrackEntry
{
    public function __construct(
        public string $trackGuid,
        public ?Track $track,
        /** @var Comment[] */
        public array $comments,
    ) {}

    public static function fromDto(TrackEntryDto $dto): self
    {
        $comments = array_map(
            fn (CommentDto $c) => Comment::fromDto($c),
            $dto->comments
        );

        return new self($dto->trackGuid, $dto->track, $comments);
    }
}
