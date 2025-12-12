<?php

namespace Assistant\Module\Mix\Model;

use DateTime;

final readonly class Mix
{
    private function __construct(
        public string $guid,
        public string $name,
        public ?string $description,
        public DateTime $created,
        public DateTime $modified,
        public ?DateTime $performed,
        public ?string $comment,
        /** @var Attempt[] */
        public array $attempts,
    ) {
    }

    public static function create(
        string $guid,
        string $name,
        ?string $description = null,
        DateTime $created = new DateTime(),
        DateTime $modified = new DateTime(),
        ?DateTime $performed = null,
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
