<?php

namespace Assistant\Module\Mix\Model;

use DateTimeImmutable;

final readonly class Mix
{
    private function __construct(
        public string $guid,
        public string $name,
        public ?string $description,
        public DateTimeImmutable $created,
        public DateTimeImmutable $modified,
        public ?DateTimeImmutable $performed,
        public ?string $comment,
        /** @var Attempt[] */
        public array $attempts,
    ) {
    }

    public static function create(
        string $guid,
        string $name,
        ?string $description = null,
        DateTimeImmutable $created = new DateTimeImmutable(),
        DateTimeImmutable $modified = new DateTimeImmutable(),
        ?DateTimeImmutable $performed = null,
        ?string $comment = null,
        array $attempts = [],
    ): self {
        if (!$attempts) {
            $emptyAttempt = Attempt::createEmpty();

            $attempts = [ $emptyAttempt ];
        }

        $mix = new self(
            guid: $guid,
            name: $name,
            description: $description,
            created: $created,
            modified: $modified,
            performed: $performed,
            comment: $comment,
            attempts: $attempts,
        );

        return $mix;
    }

    public static function fromDto(MixDto $dto): self
    {
        return self::create(
            $dto->guid,
            $dto->name,
            $dto->description,
            $dto->created,
            $dto->modified,
            $dto->performed,
            $dto->comment,
            array_map(
                fn (AttemptDto $attemptDto) => Attempt::fromDto($attemptDto),
                $dto->attempts
            )
        );
    }

    public function toDto(): MixDto
    {
        $dto = MixDto::fromModel($this);

        return $dto;
    }
}
