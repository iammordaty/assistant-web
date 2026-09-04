<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

interface ProviderInterface
{
    /** Nazwa dostawcy */
    public const NAME = '';

    /** Zwraca nazwę dostawcy */
    public function getName(): string;

    /**
     * Oblicza i zwraca wartość podobieństwa pomiędzy utworami (wyrażoną w procentach).
     *
     * `null` oznacza brak danych do porównania i jest czymś innym niż zero: dostawca nie bierze wtedy
     * udziału w wyniku, zamiast obniżać go tak, jakby utwory były maksymalnie różne.
     */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): ?int;

    /** Zwraca maksymalną wartość podobieństwa, jaką może zwrócić dostawca */
    public function getMaxSimilarityValue(): int;

    /** Zwraca kryteria, które muszą zostać spełnione, aby utwór uznać za podobny przez dostawcę */
    public function getCriteria(Track $baseTrack): mixed;
}
