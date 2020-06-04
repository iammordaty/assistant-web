<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

interface ProviderInterface
{
    /**
     * Zwraca nazwę dostawcy
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Oblicza i zwraca wartość podobieństwa pomiędzy utworami (wyrażoną w procentach)
     *
     * @param Track $baseTrack
     * @param Track $comparedTrack
     * @return int
     */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int;

    /**
     * Zwraca maksymalną wartość podobieństwa, jaką może zwrócić dostawca
     *
     * @return int
     */
    public function getMaxSimilarityValue(): int;

    /**
     * Zwraca kryteria, które muszą zostać spełnione, aby utwór uznać za podobny przez dostawcę
     *
     * @param Track $baseTrack
     * @return array
     */
    public function getCriteria(Track $baseTrack): array;
}
