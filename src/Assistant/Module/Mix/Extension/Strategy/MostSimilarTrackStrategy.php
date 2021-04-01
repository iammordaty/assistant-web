<?php

namespace Assistant\Module\Mix\Extension\Strategy;

use Assistant\Module\Track\Extension\Similarity;
use Assistant\Module\Track\Extension\SimilarTracksVO;
use Assistant\Module\Track\Model\Track;

final class MostSimilarTrackStrategy implements NextTrackStrategy
{
    private Similarity $similarity;

    private array $mix;

    private array $similarityGrid;

    public function __construct(Similarity $similarity)
    {
        $this->similarity = $similarity;
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
     *    Zerknąć na SimilarTrackVO, pewnie da się wykorzystać
     *
     * 2. Może należałoby rozdzielić tworzenie tablicy wielowymiarowej i wyliczania podobieństwa między ścieżkami?
     *
     * @param Track[][] $listing
     * @return array
     * @see SimilarTracksVO
     */
    private function computeSimilarityGrid(array $listing): array
    {
        $grid = [];

        foreach ($listing as $trackOne) {
            $row = [
                'track' => $trackOne['track'],
                'tracks' => [],
            ];

            /** @uses Track::getGuid() */
            $trackOneGuid = $trackOne['track']->getGuid();

            foreach ($listing as $trackTwo) {
                /** @uses Track::getGuid() */
                $trackTwoGuid = $trackTwo['track']->getGuid();

                $row['tracks'][$trackTwoGuid] = [
                    'track' => $trackTwo['track'],
                    'similarityValue' => $trackOneGuid !== $trackTwoGuid
                        ? $this->similarity->getSimilarityValue($trackOne['track'], $trackTwo['track'])
                        : null
                ];
            }

            $grid[$trackOneGuid] = $row;
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

    private static function addToMix(?array $track, array &$mix, array &$matrix): void
    {
        if (!$track) {
            return;
        }

        $mix[] = $track;

        foreach ($matrix as &$row) {
            /** @uses Track::getGuid() */
            unset($row['tracks'][$track['track']->getGuid()]);
        }
    }
}
