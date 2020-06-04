<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

/**
 * Klasa bazowa dla dostawców
 */
abstract class AbstractProvider implements ProviderInterface
{
    /**
     * Nazwa dostawcy
     *
     * @var string
     */
    protected const NAME = '';

    /**
     * Nazwa pola metadanych, na którym operuje dostawca
     *
     * @var string
     */
    protected const SIMILARITY_FIELD = '';

    /**
     * Maksymalna wartość podobieństwa, jaką może zwrócić dostawca
     *
     * @var int
     */
    protected const MAX_SIMILARITY_VALUE = 100;

    /**
     * Mapa podobieństwa
     *
     * @var array
     */
    protected array $similarityMap = [];

    /**
     * Zwraca nazwę dostawcy
     *
     * @return string
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * Zwraca nazwę pola metadanych, na którym operuje dostawca
     *
     * @return string
     */
    public function getSimilarityField(): string
    {
        return static::SIMILARITY_FIELD;
    }

    /**
     * Zwraca maksymalną wartość podobieństwa, jaką może zwrócić dostawca
     *
     * @return int
     */
    public function getMaxSimilarityValue(): int
    {
        return static::MAX_SIMILARITY_VALUE;
    }
}
