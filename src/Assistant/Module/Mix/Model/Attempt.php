<?php

namespace Assistant\Module\Mix\Model;

use DateTime;

final readonly class Attempt
{
    public function __construct(
        public DateTime $created,
        public ?string $comment,
        /** @var TrackEntry[] */
        public array $trackList,
    ) {}

    public static function fromDto(AttemptDto $dto): self
    {
        $trackListItems = array_map(
            fn (TrackEntryDto $dto) => TrackEntry::fromDto($dto),
            $dto->trackList
        );

        return new self($dto->created, $dto->comment, $trackListItems);
    }
}
