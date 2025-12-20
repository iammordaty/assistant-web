<?php

namespace Assistant\Module\Mix\Model;

use DateTimeImmutable;
use DateTimeZone;

final class Attempt
{
    private function __construct(
        public ?string $id,
        public readonly DateTimeImmutable $created,
        public readonly ?string $comment,
        /** @var TrackEntry[] */
        public readonly array $trackList,
    ) {
        $this->id = $id ?: uniqid('attempt_');
    }

    public static function createEmpty(): self
    {
        return new self(
            id: null,
            created: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            comment: null,
            trackList: [],
        );
    }

    public static function fromDto(AttemptDto $dto): self
    {
        $trackListItems = array_map(
            fn (TrackEntryDto $dto) => TrackEntry::fromDto($dto),
            $dto->trackList
        );

        return new self(
            id: $dto->id,
            created: $dto->created,
            comment: $dto->comment,
            trackList: $trackListItems,
        );
    }

    public function toDto(): AttemptDto
    {
        $dto = AttemptDto::fromModel($this);

        return $dto;
    }
}
