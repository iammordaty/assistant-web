<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

use SplFileInfo;

final class SimilarTracksResultList
{
    /** @var SimilarTracksResult[] */
    private array $similarTracks;

    public function __construct(SimilarTracksResult ...$similarTracks)
    {
        $this->similarTracks = array_reduce(
            $similarTracks,
            function ($similarTracks, SimilarTracksResult $similarTracksResult) {
                $similarTracks[$similarTracksResult->getSecondTrack()->getPathname()] = $similarTracksResult;

                return $similarTracks;
            },
            []
        );
    }

    /** Zwraca null dla utworu, który nie zmieścił się na liście; nie znaczy to, że jest niepodobny */
    public function getSimilarityValue(SplFileInfo $track): ?float
    {
        $similarTracksResult = $this->similarTracks[$track->getPathname()] ?? null;

        return $similarTracksResult?->getSimilarityValue();
    }

    /** @return SimilarTracksResult[] */
    public function getSimilarTracks(): array
    {
        return $this->similarTracks;
    }
}
