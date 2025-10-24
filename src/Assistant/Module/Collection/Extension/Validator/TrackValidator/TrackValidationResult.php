<?php

namespace Assistant\Module\Collection\Extension\Validator\TrackValidator;

final readonly class TrackValidationResult
{
    public function __construct(
        public bool $isValid,
        public array $errors,
    ) {
    }
}
