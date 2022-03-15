<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

interface ProviderInterface
{
    /** Nazwa dostawcy */
    public const NAME = '';

    /** Zwraca nazwę dostawcy */
    public function getName(): string;

    /** Oblicza i zwraca wartość podobieństwa pomiędzy utworami (wyrażoną w procentach) */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int;

    /** Zwraca maksymalną wartość podobieństwa, jaką może zwrócić dostawca */
    public function getMaxSimilarityValue(): int;

    /** Zwraca kryteria, które muszą zostać spełnione, aby utwór uznać za podobny przez dostawcę */
    public function getCriteria(Track $baseTrack): mixed;
}
