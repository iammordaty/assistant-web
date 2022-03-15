<?php

namespace Assistant\Module\Mix\Extension\Strategy;

use Assistant\Module\Track\Extension\Similarity\Similarity;
use Assistant\Module\Track\Extension\Similarity\SimilarTracks;
use Assistant\Module\Track\Model\Track;

final class MostSimilarTrackStrategy implements NextTrackStrategy
{
    private array $mix;

    private array $similarityGrid;

    public function __construct(private Similarity $similarity)
    {
    }

    public function compute(array $listing): void
    {
        $this->similarityGrid = $this->computeSimilarityGrid($listing);

        $this->mix = $this->computeMix();
    }

    public function getMix(): array
    {
        return $this->mix;
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
            self::addToMix($nextTrack, $mix, $similarityGrid);

            /** @var Track $track */
            $track = $nextTrack['track'];

            $nextTrack = self::computeNextTrack($similarityGrid[$track->getGuid()]['tracks']);
        }

        return $mix;
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
    private function computeSimilarityGrid(array $listing): array
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

    private static function computeNextTrack(array $tracks): ?array
    {
        $nextTrack = reset($tracks) ?: null;

        if (!$nextTrack) {
            return null;
        }

        foreach ($tracks as $track) {
            if ($track['similarityValue'] > $nextTrack['similarityValue']) {
                $nextTrack = $track;
            }
        }

        return $nextTrack;
    }

    private static function addToMix(?array $track, array &$mix, array &$similarityGrid): void
    {
        $mix[] = $track;

        foreach ($similarityGrid as &$row) {
            /** @uses Track::getGuid() */
            unset($row['tracks'][$track['track']->getGuid()]);
        }
    }
}
