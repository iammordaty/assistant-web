<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

use SplFileInfo;

final class SimilarTracksResultList
{
    private SplFileInfo $baseTrack;

    private array $similarTracks;

    public function __construct(SplFileInfo $baseTrack, SimilarTracksResult ...$similarTracks)
    {
        $this->baseTrack = $baseTrack;
        $this->similarTracks = array_reduce(
            $similarTracks,
            function ($similarTracks, SimilarTracksResult $similarTracksResult) {
                $similarTracks[$similarTracksResult->getSecondTrack()->getPathname()] = $similarTracksResult;

                return $similarTracks;
            },
            []
        );
    }

    public static function factory(SplFileInfo|string $baseTrack, array $similarTracks): self
    {
        if (is_string($baseTrack)) {
            $baseTrack = new SplFileInfo($baseTrack);
        }

        $similarTracks = array_map(function ($similarTrack) use ($baseTrack): SimilarTracksResult {
            $similarTracksResult = SimilarTracksResult::factory(
                $baseTrack,
                $similarTrack['track-origin'],
                $similarTrack['track-distance']
            );

            return $similarTracksResult;
        }, $similarTracks);

        return new self($baseTrack, ...$similarTracks);
    }

    public function getBaseTrack(): SplFileInfo
    {
        return $this->baseTrack;
    }

    public function getSimilarityResult(SplFileInfo $track): ?SimilarTracksResult
    {
        return $this->similarTracks[$track->getPathname()] ?? null;
    }

    public function getSimilarityValue(SplFileInfo $track): float
    {
        return $this->getSimilarityResult($track)?->getSimilarityValue() ?? 0;
    }

    /** @return SimilarTracksResult[] */
    public function getSimilarTracks(): array
    {
        return $this->similarTracks;
    }
}
