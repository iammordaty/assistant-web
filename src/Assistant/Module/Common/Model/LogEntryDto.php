<?php

namespace Assistant\Module\Common\Model;

use MongoDB\Model\BSONArray;
use stdClass;

final class LogEntryDto
{
    public function __construct(
        public readonly string $objectId,
        public readonly string $message,
        public readonly array $context,
        public readonly string $levelName,
        public readonly \DateTime $datetime,
        public readonly array $extra,
        public readonly ?string $taskName,
        public readonly ?array $pathname,
    ) {
    }

    public static function fromStorage(stdClass $document): self
    {
        /** @var BSONArray $context */
        $context = $document->context;

        /** @var BSONArray $extra */
        $extra = $document->context;

        $dto = new self(
            objectId: $document->_id,
            message: $document->message,
            context: $context->getArrayCopy(),
            levelName: $document->level_name,
            datetime: $document->datetime->toDateTime(),
            extra: $extra->getArrayCopy(),
            taskName: null,
            pathname: null,
        );

        return $dto;
    }

    public static function fromModel(LogEntry $logEntry): self
    {
        $dto = new self(
            objectId: $logEntry->getId(),
            message: $logEntry->getMessage(),
            context: $logEntry->getContext(),
            levelName: $logEntry->getLevelName(),
            datetime: $logEntry->getDatetime(),
            extra: $logEntry->getExtra(),
            taskName: $logEntry->getTaskName(),
            pathname: $logEntry->getPathname(),
        );

        return $dto;
    }
}
