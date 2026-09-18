<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

use SplFileInfo;

// Sprawdzić, czy SimilarTracks może używać tego jako klasy bazowej albo serwisu (jak Config, Finder itp.)
// zwracając oczywiście obiekty typu Track, a nie SplFileInfo. Jeśli tak, to należałoby uspójnić nazewnictwo klas.
final readonly class SimilarTracksResult
{
    public function __construct(
        private SplFileInfo $firstTrack,
        private SplFileInfo $secondTrack,
        private float $similarityValue,
    ) {
    }

    // do zastanowienia się, czy to utrzymywać
    public function getFirstTrack(): SplFileInfo
    {
        return $this->firstTrack;
    }

    public function getSecondTrack(): SplFileInfo
    {
        return $this->secondTrack;
    }

    public function getSimilarityValue(): float
    {
        return $this->similarityValue;
    }
}
