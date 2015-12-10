<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\Provider as BaseProvider;
use Assistant\Module\Track;

class Bpm extends BaseProvider
{
    /**
     * {@inheritDoc}
     */
    const METADATA_FIELD = 'bpm';

    /**
     * {@inheritDoc}
     */
    protected $similarityMap = [
        0 => self::MAX_SIMILARITY_VALUE,
        1 => 98,
        2 => 93,
        3 => 70,
        4 => 60,
        5 => 30,
    ];

    /**
     * {@inheritDoc}
     */
    public function getSimilarity(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack)
    {
        $distance = (int) round(abs($baseTrack->bpm - $comparedTrack->bpm));
        $similarity = isset($this->similarityMap[$distance]) ? $this->similarityMap[$distance] : 0;

        // echo $baseTrack->bpm, ' vs. ', $comparedTrack->bpm, ' = ', $similarity, " ($distance)", PHP_EOL;

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track\Model\Track $baseTrack)
    {
        return [
            '$in' => range(
                round($baseTrack->bpm) - $this->parameters['tolerance'],
                round($baseTrack->bpm) + $this->parameters['tolerance']
            )
        ];
    }
}
