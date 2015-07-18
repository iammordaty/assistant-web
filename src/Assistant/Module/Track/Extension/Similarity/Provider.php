<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track;

/**
 * Klasa bazowa dla dostawców podobieństwa
 */
abstract class Provider
{
    /**
     * Maksymalna wartość podobieństwa, jaką może zwrócić dostawca
     */
    const MAX_SIMILARITY_VALUE = 100;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * Mapa podobieństwa
     *
     * @var array
     */
    protected $similarityMap = [ ];

    /**
     * Konstruktor
     *
     * @param array|null $parameters
     */
    public function __construct(array $parameters = null)
    {
        if ($parameters !== null) {
            $this->parameters = $parameters;
        }

        $this->setup();
    }

    /**
     * Oblicza podobieństwo pomiędzy utworami
     *
     * @param Track\Model\Track $baseTrack
     * @param Track\Model\Track $comparedTrack
     * @return int
     */
    abstract public function getSimilarity(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack);

    /**
     * Zwraca kryteria, które muszą zostać spełnione, aby utwór uznać za podobny przez dostawcę
     *
     * @param Track\Model\Track $baseTrack
     * @return array
     */
    abstract public function getCriteria(Track\Model\Track $baseTrack);

    /**
     * Zwraca nazwę pola metadanych, na którym operuje dostawca
     *
     * @return string
     */
    abstract public function getMetadataField();

    /**
     * Przygotowuje dane dostawcy do użycia
     */
    protected function setup()
    {

    }
}
