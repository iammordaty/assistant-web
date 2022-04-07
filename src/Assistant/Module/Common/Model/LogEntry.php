<?php

namespace Assistant\Module\Common\Model;

final class LogEntry
{
    private readonly ?string $taskName;
    private readonly ?array $pathname;

    public function __construct(
        private readonly string $id,
        private readonly string $message,
        private readonly array $context,
        private readonly string $levelName,
        private readonly \DateTime $datetime,
        private readonly array $extra,
    ) {
        if (isset($context['pathname'])) {
            $short = basename(dirname($context['pathname'])) . DIRECTORY_SEPARATOR . basename($context['pathname']);

            $pathname = [
                'short' => $short,
                'full' => $context['pathname'],
            ];
        }

        $this->pathname = $pathname ?? [];
        $this->taskName = $context['command'] ?? null;
    }

    public static function fromDto(LogEntryDto $dto): self
    {
        $dto = new self(
            id: $dto->objectId,
            message: $dto->message,
            context: $dto->context,
            levelName: $dto->levelName,
            datetime: $dto->datetime,
            extra: $dto->extra
        );

        return $dto;
    }

    public function toDto(): LogEntryDto
    {
        $dto = LogEntryDto::fromModel($this);

        return $dto;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getLevelName(): string
    {
        return $this->levelName;
    }

    public function getDatetime(): \DateTime
    {
        return $this->datetime;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }

    public function getTaskName(): ?string
    {
        return $this->taskName;
    }

    public function getPathname(): ?array
    {
        return $this->pathname;
    }
}
