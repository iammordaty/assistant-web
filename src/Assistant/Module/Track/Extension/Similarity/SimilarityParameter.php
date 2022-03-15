<?php

namespace Assistant\Module\Track\Extension\Similarity;

final class SimilarityParameter
{
    public function __construct(
        public readonly string $name,
        public readonly string $displayName,
        public readonly ?string $inputType = null,
        public readonly string|int|null $inputValue = null,
        public readonly string|int|null $placeholder = null,
        public readonly ?int $inputMinValue = null,
        public readonly ?int $inputMaxValue = null,
        public readonly ?int $inputStep = null,
    ) {
    }

    /** @noinspection PhpUnused, Used in the template */
    public function hasInput(): bool
    {
        return $this->inputType !== null;
    }
}
