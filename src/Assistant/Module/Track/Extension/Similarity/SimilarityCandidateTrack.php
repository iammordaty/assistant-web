<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Model\Track;

/** Kandydat wraz z przyznaną mu wartością podobieństwa do utworu bazowego */
final class SimilarityCandidateTrack
{
    public function __construct(
        private Track $track,
        private float $similarityValue,
    ) {
    }

    public function getTrack(): Track
    {
        return $this->track;
    }

    public function getSimilarityValue(): float
    {
        return $this->similarityValue;
    }
}
