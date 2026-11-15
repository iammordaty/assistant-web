<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

final readonly class MusicClassifierTag
{
    public const string TYPE_GENRE = 'genre';

    public function __construct(
        private string $name,
        private string $type,
        private float $confidence,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** genre, mood, instrument albo tag */
    public function getType(): string
    {
        return $this->type;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }
}
