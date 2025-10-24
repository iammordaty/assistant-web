<?php

namespace Assistant\Module\Collection\Extension\Validator\Exception;

use SplFileInfo;
use Throwable;
use UnexpectedValueException;

/** Wyjątek rzucany w sytuacji, gdy utwór nie zawiera metadanych lub są one nieprawidłowe */
final class InvalidMetadataException extends UnexpectedValueException
{
    public function __construct(
        string $message,
        public readonly SplFileInfo $track,
        public readonly array $errors,
        int $code = 0,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
