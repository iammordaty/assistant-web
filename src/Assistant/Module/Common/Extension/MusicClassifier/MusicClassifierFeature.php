<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

final class MusicClassifierFeature
{
    public function __construct(private string $name, private int $probability)
    {
    }

    public static function create(string $name, float $probability): self
    {
        $probability = round($probability * 100);

        return new self($name, $probability);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProbability(): float
    {
        return $this->probability;
    }
}
