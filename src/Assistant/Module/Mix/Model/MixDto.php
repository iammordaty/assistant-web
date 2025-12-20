<?php

namespace Assistant\Module\Mix\Model;

use DateTime;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use stdClass;

final class MixDto
{
    public function __construct(
        public string $guid,
        public string $name,
        public ?string $description,
        public DateTimeImmutable $created,
        public DateTimeImmutable $modified,
        public ?DateTimeImmutable $performed,
        public ?string $comment,
        /** @var AttemptDto[] */
        public array $attempts,
    ) {
    }

    public static function fromStorage(stdClass $document): self
    {
        /** @var BSONArray $attempts */
        $attempts = $document->attempts;

        $attempts = array_map(
            fn (BSONDocument $attempt) => AttemptDto::fromStorage($attempt),
            $attempts->getArrayCopy(),
        );

        return new self(
            guid: $document->guid,
            name: $document->name,
            description: $document->description,
            created: $document->created->toDateTimeImmutable(),
            modified: $document->modified->toDateTimeImmutable(),
            performed: isset($document->performed) ? $document->performed->toDateTimeImmutable() : null,
            comment: $document->comment,
            attempts: $attempts,
        );
    }

    public static function fromModel(Mix $mix): self
    {
        $attempts = array_map(
            fn (Attempt $attempt) => $attempt->toDto(),
            $mix->attempts
        );

        return new self(
            $mix->guid,
            $mix->name,
            $mix->description,
            $mix->created,
            $mix->modified,
            $mix->performed,
            $mix->comment,
            $attempts,
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
            'performed' => $this->performed ? new UTCDateTime($this->performed->getTimestamp() * 1000) : null,
            'comment' => $this->comment,
            'attempts' => array_map(
                fn (AttemptDto $dto) => $dto->toStorage(),
                $this->attempts,
            ),
        ];
    }

    public function toJson(): array
    {
        $attempts = array_map(
            fn (AttemptDto $attempt) => $attempt->toJson(),
            $this->attempts,
        );

        return [
            'guid' => $this->guid,
            'name' => $this->name,
            'description' => $this->description,
            'created' => $this->created->format(DateTime::ATOM),
            'modified' => $this->modified->format(DateTime::ATOM),
            'performed' => $this->performed?->format(DateTime::ATOM),
            'comment' => $this->comment,
            'attempts' => $attempts,
        ];
    }
}
