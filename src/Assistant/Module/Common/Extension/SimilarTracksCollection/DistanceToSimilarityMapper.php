<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

/**
 * Zamienia odległość zwróconą przez musly na podobieństwo w skali 0-100,
 * gdzie 100 oznacza najbardziej podobną parę
 */
final readonly class DistanceToSimilarityMapper
{
    public function __invoke(int $collectionSize, float $distance): float
    {
        $bestPossible = 2.0 / $collectionSize;
        $distance = min(max($distance, $bestPossible), 1.0);

        $normalized = log10(1.0 / $distance) / log10($collectionSize / 2.0);

        return round(100 * max(0.0, min(1.0, $normalized)), 2);
    }
}
