<?php

namespace Assistant\Module\Mix\Extension\Strategy;

use Assistant\Module\Track\Extension\Similarity;
use Assistant\Module\Track\Model\Track;

final class MostSimilarTrackStrategy implements NextTrackStrategy
{
    private Similarity $similarity;

    public function __construct(Similarity $similarity)
    {
        $this->similarity = $similarity;
    }

    public function computeMix(array $matrix): array
    {
        $mix = [];

        $nextTrack = reset($matrix);

        while ($nextTrack) {
            static::addToMix($nextTrack, $mix, $matrix);

            $nextTrack = static::computeNextTrack($matrix[$nextTrack['track']->guid]['tracks']);
        }

        return $mix;
    }

    /**
     *
     * @param Track[] $listing
     * @return array
     */
    public function computeMatrix(array $listing): array
    {
        $matrix = [];

        foreach ($listing as $trackOne) {
            $row = [
                'track' => $trackOne['track'],
                'tracks' => [],
            ];

            foreach ($listing as $trackTwo) {
                $row['tracks'][$trackTwo['track']->guid] = [
                    'track' => $trackTwo['track'],
                    'similarityValue' => $trackOne['track']->guid !== $trackTwo['track']->guid
                        ? $this->similarity->getSimilarityValue($trackOne['track'], $trackTwo['track'])
                        : null
                ];
            }

            $matrix[$trackOne['track']->guid] = $row;
        }

        return $matrix;
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
            unset($row['tracks'][$track['track']->guid]);
        }
    }
}