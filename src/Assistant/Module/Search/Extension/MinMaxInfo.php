<?php

namespace Assistant\Module\Search\Extension;

final class MinMaxInfo
{
    // @idea Może BackedEnum zamiast const-ów?
    public const GREATER_THAN = 'gt';
    public const GREATER_THAN_OR_EQUAL = 'gte';
    public const LESS_THAN = 'lt';
    public const LESS_THAN_OR_EQUAL = 'lte';

    private const VALID_OPERATORS = [
        self::GREATER_THAN,
        self::GREATER_THAN_OR_EQUAL,
        self::LESS_THAN,
        self::LESS_THAN_OR_EQUAL,
    ];

    public function __construct(private array $minMaxInfo)
    {
        if (($invalid = array_diff(array_keys($minMaxInfo), self::VALID_OPERATORS))) {
            throw new \InvalidArgumentException('Invalid MinMaxInfo operator: ', implode(',', $invalid));
        }
    }

    public static function create(array $minMaxInfo): self
    {
        return new self($minMaxInfo);
    }

    public function get(): array
    {
        return $this->minMaxInfo;
    }

    public function operators(): array
    {
        return array_keys($this->minMaxInfo);
    }

    public function values(): array
    {
        return array_values($this->minMaxInfo);
    }

    public function isEqual(): bool
    {
        [ $min, $max ] = $this->values();
        $result = $min === $max;

        return $result;
    }
}
