<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Model\Track;

final class SimilarTracks
{
    public function __construct(
        private Track $firstTrack,
        private Track $secondTrack,
        private float $similarityValue,
    ) {
    }

    public function getFirstTrack(): Track
    {
        return $this->firstTrack;
    }

    public function getSecondTrack(): Track
    {
        return $this->secondTrack;
    }

    public function getSimilarityValue(): float
    {
        return $this->similarityValue;
    }
}
