<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

/** Klasa bazowa dla dostawców */
abstract class AbstractProvider implements ProviderInterface
{
    /** Nazwa dostawcy */
    public const NAME = '';

    /** Maksymalna wartość podobieństwa, jaką może zwrócić dostawca */
    protected const MAX_SIMILARITY_VALUE = 100;

    /** Mapa podobieństwa */
    protected array $similarityMap = [];

    /** Zwraca nazwę dostawcy */
    public function getName(): string
    {
        return static::NAME;
    }

    /** Zwraca maksymalną wartość podobieństwa, jaką może zwrócić dostawca */
    public function getMaxSimilarityValue(): int
    {
        return static::MAX_SIMILARITY_VALUE;
    }
}
