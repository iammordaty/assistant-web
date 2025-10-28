<?php

namespace Assistant\Module\Mix\Model;

use DateTime;

final readonly class Mix
{
    public function __construct(
        public string $guid,
        public string $name,
        public string $description,
        public DateTime $created,
        public DateTime $modified,
        public ?DateTime $performed,
        public ?string $comment,
        /** @var Attempt[] */
        public array $attempts,
    ) {}

    public function withAttempt(Attempt $attempt): self
    {
        $attempts = $this->attempts;
        $attempts[] = $attempt;

        return new self(
            $this->guid,
            $this->name,
            $this->description,
            $this->created,
            new DateTime(),
            $this->performed,
            $this->comment,
            $attempts
        );
    }

    public static function fromDto(MixDto $dto): self
    {
        return new self(
            $dto->guid,
            $dto->name,
            $dto->description,
            $dto->created,
            $dto->modified,
            $dto->performed,
            $dto->comment,
            array_map(
                fn (AttemptDto $v) => Attempt::fromDto($v),
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
