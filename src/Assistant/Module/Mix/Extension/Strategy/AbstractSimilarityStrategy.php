<?php

namespace Assistant\Module\Mix\Extension\Strategy;

use Assistant\Module\Track\Extension\Similarity\Similarity;
use Assistant\Module\Track\Model\Track;

abstract class AbstractSimilarityStrategy implements NextTrackStrategy
{
    protected array $mix = [];

    protected array $similarityGrid = [];

    public function __construct(protected Similarity $similarity) {}

    public function getMix(): array
    {
        return $this->mix;
    }

    public function getSimilarityGrid(): array
    {
        return $this->similarityGrid;
    }

    /**
     * 1. Do przemyślenia czy da się to osiągnąć bardziej czytelnie i elegancko.
     *    Zerknąć na SimilarTracks, pewnie da się wykorzystać
     *
     * 2. Może należałoby rozdzielić tworzenie tablicy wielowymiarowej i wyliczania podobieństwa między ścieżkami?
     *
     * @param Track[] $listing
     * @return array
     * @see SimilarTracks
     */
    protected function computeSimilarityGrid(array $listing): array
    {
        $grid = [];

        foreach ($listing as $trackOne) {
            $row = [
                'track' => $trackOne,
                'tracks' => [],
            ];

            foreach ($listing as $trackTwo) {
                $similarityValue = $trackOne->getGuid() !== $trackTwo->getGuid()
                    ? $this->similarity->getSimilarityValue($trackOne, $trackTwo)
                    : null;

                $row['tracks'][$trackTwo->getGuid()] = [
                    'track' => $trackTwo,
                    'similarityValue' => $similarityValue,
                ];
            }

            $grid[$trackOne->getGuid()] = $row;
        }

        return $grid;
    }

    protected static function computeNextTrack(array $tracks): ?array
    {
        $nextTrack = null;
        $maxSimilarity = -1;

        foreach ($tracks as $track) {
            if (isset($track['similarityValue']) && $track['similarityValue'] > $maxSimilarity) {
                $maxSimilarity = $track['similarityValue'];
                $nextTrack = $track;
            }
        }

        return $nextTrack;
    }

    protected static function addToMix(?array $track, array &$mix, array &$similarityGrid): void
    {
        $mix[] = $track;

        foreach ($similarityGrid as &$row) {
            /** @uses Track::getGuid() */
            unset($row['tracks'][$track['track']->getGuid()]);
        }

        unset($row);
    }
}
