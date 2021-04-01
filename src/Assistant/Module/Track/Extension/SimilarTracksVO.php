<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track\Model\Track;

final class SimilarTracksVO
{
    private Track $firstTrack;

    private Track $secondTrack;

    private float $similarityValue;

    public function __construct(Track $firstTrack, Track $secondTrack, float $similarityValue)
    {
        $this->firstTrack = $firstTrack;
        $this->secondTrack = $secondTrack;
        $this->similarityValue = $similarityValue;
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
