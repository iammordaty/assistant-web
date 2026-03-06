<?php

namespace Assistant\Module\Search\Extension\Request\ExpressionParser;

final readonly class NameParseResult
{
    /**
     * @param array<string,string> $modifiers
     */
    public function __construct(
        private ?string $freeText,
        private array $modifiers,
    ) {
    }

    public function getFreeText(): ?string
    {
        return $this->freeText;
    }

    /** @return array<string,string> */
    public function getModifiers(): array
    {
        return $this->modifiers;
    }
}
