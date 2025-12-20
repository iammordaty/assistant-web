<?php

namespace Assistant\Module\Mix\Model;

use Assistant\Module\Mix\Extension\Mix\AttemptSaveRequest;
use DateTime;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

final class AttemptDto
{
    private function __construct(
        public ?string $id,
        public DateTimeImmutable $created,
        public ?string $comment,
        /** @var TrackEntryDto[] */
        public array $trackList,
    ) {
    }

    public static function fromStorage(BSONDocument $attempt): self
    {
        /** @var UTCDateTime $created */
        $created = $attempt['created'];

        /** @var BSONArray $trackList */
        $trackList = $attempt['trackList'];

        $trackList = array_map(
            fn (BSONDocument $entry) => TrackEntryDto::fromStorage($entry),
            $trackList->getArrayCopy()
        );

        return new self(
            $attempt['id'],
            $created->toDateTimeImmutable(),
            $attempt['comment'],
            $trackList,
        );
    }

    public static function fromModel(Attempt $attempt): self
    {
        $trackList = array_map(
            fn (TrackEntry $trackEntry) => TrackEntryDto::fromModel($trackEntry),
            $attempt->trackList
        );

        return new self(
            id: $attempt->id,
            created: $attempt->created,
            comment: $attempt->comment,
            trackList: $trackList,
        );
    }

    public static function fromRequest(AttemptSaveRequest $request): self
    {
        $trackList = array_map(
            fn (array $entry) => TrackEntryDto::fromJson($entry),
            $request->trackList
        );

        return new self(
            $request->id,
            $request->created,
            $request->comment,
            $trackList,
        );
    }

    public function toStorage(): array
    {
        $trackList = array_map(
            fn (TrackEntryDto $dto) => $dto->toStorage(),
            $this->trackList
        );

        return [
            'id' => $this->id,
            'created' => new UTCDateTime($this->created->getTimestamp() * 1000),
            'comment' => $this->comment,
            'trackList' => $trackList,
        ];
    }

    public function toJson(): array
    {
        $trackList = array_map(
            fn (TrackEntryDto $dto) => $dto->toJson(),
            $this->trackList,
        );

        return [
            'id' => $this->id,
            'created' => $this->created->format(DateTime::ATOM),
            'comment' => $this->comment,
            'trackList' => $trackList,
        ];
    }
}
