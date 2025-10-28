<?php

namespace Assistant\Module\Mix\Model;

use Assistant\Module\Mix\Extension\Mix\AttemptSaveRequest;
use DateTime;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

final class AttemptDto
{
    public function __construct(
        public DateTime $created,
        public ?string $comment,
        /** @var TrackEntryDto[] */
        public array $trackList,
    ) {
    }

    public static function fromStorage(BSONDocument $version): self
    {
        /** @var BSONArray $trackList */
        $trackList = $version->trackList;

        return new self(
            $version->created->toDateTime(),
            $version->comment ?? null,
            array_map(
                fn ($item) => TrackEntryDto::fromStorage($item),
                $trackList->getArrayCopy()
            )
        );
    }

    public static function fromModel(Attempt $attempt): self
    {
        $trackList = array_map(
            fn (TrackEntry $trackEntry) => TrackEntryDto::fromModel($trackEntry),
            $attempt->trackList
        );

        return new self(
            $attempt->created,
            $attempt->comment,
            $trackList
        );
    }

    public static function fromRequest(AttemptSaveRequest $request): self
    {
        $trackListDto = array_map(function($trackEntryData) {
            $comments = array_map(function($commentData) {
                return new CommentDto(
                    $commentData['time'] ?? 0,
                    $commentData['type'] ?? 'general',
                    $commentData['content']
                );
            }, $trackEntryData['comments']);

            return new TrackEntryDto(
                $trackEntryData['trackGuid'],
                null, // track będzie załadowany przez MixService
                $comments
            );
        }, $request->trackList);

        return new self(
            new DateTime($request->created),
            $request->comment,
            $trackListDto
        );
    }

    public function toStorage(): array
    {
        $trackList = array_map(
            fn (TrackEntryDto $dto) => $dto->toStorage(),
            $this->trackList
        );

        return [
            'created' => new UTCDateTime($this->created->getTimestamp() * 1000),
            'comment' => $this->comment,
            'trackList' => $trackList,
        ];
    }
}
