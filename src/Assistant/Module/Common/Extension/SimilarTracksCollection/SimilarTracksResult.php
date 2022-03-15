<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

use SplFileInfo;

// Sprawdzić, czy SimilarTracks może używać tego jako klasy bazowej albo serwisu (jak Config, Finder itp.)
// zwracając oczywiście obiekty typu Track, a nie SplFileInfo. Jeśli tak, to należałoby uspójnić nazewnictwo klas.
final class SimilarTracksResult
{
    private SplFileInfo $firstTrack;

    private SplFileInfo $secondTrack;

    private float $similarityValue;

    public function __construct(SplFileInfo $firstTrack, SplFileInfo $secondTrack, float $similarityValue)
    {
        $this->firstTrack = $firstTrack;
        $this->secondTrack = $secondTrack;
        $this->similarityValue = $similarityValue;
    }

    public static function factory(
        SplFileInfo|string $firstTrack,
        SplFileInfo|string $secondTrack,
        float $distance
    ): self {
        if (is_string($firstTrack)) {
            $firstTrack = new SplFileInfo($firstTrack);
        }
        if (is_string($secondTrack)) {
            $secondTrack = new SplFileInfo($secondTrack);
        }

        $similarityValue = round(100 - ($distance * 100), 2);

        return new self($firstTrack, $secondTrack, $similarityValue);
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
