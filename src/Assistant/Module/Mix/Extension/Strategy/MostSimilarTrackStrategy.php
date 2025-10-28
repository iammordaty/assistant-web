<?php

namespace Assistant\Module\Mix\Extension\Strategy;

use Assistant\Module\Track\Model\Track;

final class MostSimilarTrackStrategy extends AbstractSimilarityStrategy implements NextTrackStrategy
{
    public function compute(array $listing): void
    {
        $this->similarityGrid = $this->computeSimilarityGrid($listing);

        $this->mix = $this->computeMix();
    }

    public function getSimilarityGrid(): array
    {
        return $this->similarityGrid;
    }

    private function computeMix(): array
    {
        $similarityGrid = $this->similarityGrid;

        $mix = [];

        $nextTrack = reset($similarityGrid);

        while ($nextTrack) {
            $this->addToMix($nextTrack, $mix, $similarityGrid);

            /** @var Track $track */
            $track = $nextTrack['track'];

            $nextTrack = $this->computeNextTrack($similarityGrid[$track->getGuid()]['tracks'] ?? []);
        }

        return $mix;
    }
}
