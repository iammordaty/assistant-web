<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

final class MusicClassifierFeature
{
    public const TYPE_DANCEABILITY = 'danceability';
    public const TYPE_GENRE = 'genre';
    public const TYPE_MOOD = 'mood';
    public const TYPE_MOOD_CLUSTER = 'mood_cluster';
    public const TYPE_TONALITY = 'tonality';
    public const TYPE_TONE_COLOR = 'tone_color';
    public const TYPE_VOCAL = 'gender';

    public function __construct(
        private string $name,
        private ?string $type,
        private int $probability,
    ) {
    }

    public static function create(string $name, int $probability, ?string $type = null): self
    {
        return new self($name, $type, $probability);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getProbability(): float
    {
        return $this->probability;
    }
}
