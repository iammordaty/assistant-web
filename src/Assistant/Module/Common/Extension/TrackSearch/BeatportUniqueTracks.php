<?php

namespace Assistant\Module\Common\Extension\TrackSearch;

use Assistant\Module\Common\Extension\Beatport\BeatportTrack;

final class BeatportUniqueTracks
{
    /** @var BeatportTrack[] */
    private array $beatportTracks = [];

    /**
     * @param BeatportTrack[] $beatportTracks
     * @return BeatportUniqueTracks
     */
    public function add(array $beatportTracks): BeatportUniqueTracks
    {
        foreach ($beatportTracks as $beatportTrack) {
            if (!($beatportTrack instanceof BeatportTrack)) {
                throw new \RuntimeException('Unsupported object type: ' . get_class($beatportTrack));
            }
        }

        array_push($this->beatportTracks, ...$beatportTracks);

        return $this;
    }

    /**
     * @return BeatportTrack[]
     */
    public function get(): array
    {
        $unique = [];

        // upewnić się, że id jest unikalne, tzn. utwór od danym id przynależy do jednego release-id
        foreach ($this->beatportTracks as $beatportTrack) {
            $unique[$beatportTrack->getId()] = $beatportTrack;
        }

        return array_values($unique);
    }
}