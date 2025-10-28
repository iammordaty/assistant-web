<?php

namespace Assistant\Module\Mix\Model;

use Assistant\Module\Track\Model\Track;
use DateTime;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use stdClass;

final class MixDto
{
    public function __construct(
        public string $guid,
        public string $name,
        public string $description,
        public DateTime $created,
        public DateTime $modified,
        public ?DateTime $performed,
        public ?string $comment,
        /** @var AttemptDto[] */
        public array $attempts,
    ) {
    }

    public static function fromStorage(stdClass $document): self
    {
        /** @var BSONArray $versions */
        $versions = $document->versions;

        return new self(
            guid: $document->guid,
            name: $document->name,
            description: $document->description,
            created: $document->created->toDateTime(),
            modified: $document->modified->toDateTime(),
            performed: isset($document->performed) ? $document->performed->toDateTime() : null,
            comment: $document->comment ?? null,
            attempts: array_map(
                fn ($attemptDto) => AttemptDto::fromStorage($attemptDto),
                $versions->getArrayCopy()
            )
        );
    }

    public static function fromModel(Mix $mix): self
    {
        return new self(
            $mix->guid,
            $mix->name,
            $mix->description,
            $mix->created,
            $mix->modified,
            $mix->performed,
            $mix->comment,
            array_map(
                fn (Attempt $attempt) => AttemptDto::fromModel($attempt),
                $mix->attempts
            )
        );
    }

    public function toStorage(): array
    {
        return [
            'guid' => $this->guid,
            'name' => $this->name,
            'description' => $this->description,
            'created' => new UTCDateTime($this->created->getTimestamp() * 1000),
            'modified' => new UTCDateTime($this->modified->getTimestamp() * 1000),
            'performed' => new UTCDateTime($this->performed->getTimestamp() * 1000),
            'comment' => $this->comment,
            'versions' => array_map(
                fn (AttemptDto $attemptDto) => $attemptDto->toStorage(),
                $this->attempts
            ),
        ];
    }

    /**
     * @fixme Na szybko, nie przywiązywać się
     */
    public function toJson(): array
    {
        $dateTimeToAtom = fn (DateTime $dateTime): string => $dateTime->format(DateTime::ATOM);

        $commentToJson = fn (CommentDto $comment): array => [
            'time' => $comment->time,
            'type' => $comment->type,
            'content' => $comment->content,
        ];

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
            'modifiedDate' => $dateTimeToAtom($track->getModifiedDate()),
            'indexedDate' => $track->getIndexedDate() ? $dateTimeToAtom($track->getIndexedDate()) : null,
        ];

        $trackEntryToJson = fn (TrackEntryDto $trackEntry): array => [
            'trackGuid' => $trackEntry->trackGuid,
            'track' => $trackEntry->track ? $trackToJson($trackEntry->track) : null,
            'comments' => array_map($commentToJson, $trackEntry->comments),
        ];

        $attemptToJson = fn(AttemptDto $attempt, int $number): array => [
            'id' => uniqid(),
            'number' => $number,
            'created' => $dateTimeToAtom($attempt->created),
            'comment' => $attempt->comment,
            'trackList' => array_map($trackEntryToJson, $attempt->trackList),
        ];

        $attemptsWithNumbers = array_map(
            fn(AttemptDto $attempt, int $index) => $attemptToJson($attempt, $index + 1),
            $this->attempts,
            array_keys($this->attempts)
        );

        return [
            'guid' => $this->guid,
            'name' => $this->name,
            'description' => $this->description,
            'created' => $dateTimeToAtom($this->created),
            'modified' => $dateTimeToAtom($this->modified),
            'performed' => $this->performed ? $dateTimeToAtom($this->performed) : null,
            'comment' => $this->comment,
            'attempts' => $attemptsWithNumbers,
        ];
    }
}
