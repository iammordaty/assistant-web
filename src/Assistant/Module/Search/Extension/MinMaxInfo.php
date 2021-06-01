<?php

namespace Assistant\Module\Search\Extension;

final class MinMaxInfo
{
    private const VALID_INFO_KEYS = [
        'gt',
        'gte',
        'lt',
        'lte',
    ];

    public function __construct(private array $minMaxInfo)
    {
        if (($invalid = array_diff(array_keys($minMaxInfo), self::VALID_INFO_KEYS))) {
            throw new \InvalidArgumentException('Invalid MinMaxExpressionInfo keys: ', implode(',', $invalid));
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

    public function keys(): array
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
