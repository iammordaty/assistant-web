<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

use SplFileInfo;

final class SimilarTracksResultList
{
    private const string MAX_DISTANCE = '-nan';

    private SplFileInfo $baseTrack;

    /** @var SimilarTracksResult[] */
    private array $similarTracks;

    private function __construct(SplFileInfo $baseTrack, SimilarTracksResult ...$similarTracks)
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

        // sytuacja, w której jako dystans zwracany jest "-nan" powinna być obsłużona po stronie musly (cpp)
        $similarTracks = array_filter(
            $similarTracks,
            fn (array $similarTrack): bool => $similarTrack['track-distance'] !== self::MAX_DISTANCE
        );

        $similarTracks = array_map(
            fn (array $similarTrack): SimilarTracksResult =>
            SimilarTracksResult::factory($baseTrack, $similarTrack['track-origin'], $similarTrack['track-distance']),
            $similarTracks
        );

        return new self($baseTrack, ...$similarTracks);
    }

    public function getBaseTrack(): SplFileInfo
    {
        return $this->baseTrack;
    }

    public function getSimilarityValue(SplFileInfo $track): float
    {
        $similarTracksResult = $this->similarTracks[$track->getPathname()] ?? null;

        if (!$similarTracksResult) {
            return 0;
        }

        return $similarTracksResult->getSimilarityValue();
    }

    /** @return SimilarTracksResult[] */
    public function getSimilarTracks(): array
    {
        return $this->similarTracks;
    }
}
