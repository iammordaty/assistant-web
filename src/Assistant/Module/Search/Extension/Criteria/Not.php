<?php

namespace Assistant\Module\Search\Extension\Criteria;

final readonly class Not implements SearchCriteriaField
{
    private function __construct(private mixed $value)
    {
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('Value must be scalar.');
        }
    }

    public static function equal(mixed $value): self
    {
        return new self($value);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
