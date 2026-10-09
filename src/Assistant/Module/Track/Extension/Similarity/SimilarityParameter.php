<?php

namespace Assistant\Module\Track\Extension\Similarity;

final readonly class SimilarityParameter
{
    public function __construct(
        public string $name,
        public string $displayName,
        public ?string $inputType = null,
        public string|int|float|null $inputValue = null,
        public string|int|float|null $placeholder = null,
        public ?float $inputMinValue = null,
        public ?float $inputMaxValue = null,
        public ?float $inputStep = null,
        /** @var array<string, string> wartość => opis, renderowane jako natywny `<datalist>` */
        public array $inputSuggestions = [],
    ) {
    }

    /** @noinspection PhpUnused, Used in the template */
    public function hasInput(): bool
    {
        return $this->inputType !== null;
    }
}
