<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\Provider as BaseProvider;
use Assistant\Module\Track;

class Year extends BaseProvider
{
    /**
     * {@inheritDoc}
     */
    const METADATA_FIELD = 'year';

    /**
     * {@inheritDoc}
     */
    protected $similarityMap = [
        1 => 98,
        2 => 90,
        3 => 70,
        4 => 40,
        5 => 20,
    ];
    
    /**
     * {@inheritDoc}
     */
    public function getSimilarity(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack)
    {
        if ($comparedTrack->year === $baseTrack->year) {
            return static::MAX_SIMILARITY_VALUE;
        }

        $distance = abs($baseTrack->year - $comparedTrack->year);
        $similarity = isset($this->similarityMap[$distance]) ? $this->similarityMap[$distance] : 0;

        // echo $baseTrack->year, ' vs. ', $comparedTrack->year, ' = ', $similarity, " ($distance)", PHP_EOL;

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track\Model\Track $baseTrack)
    {
        $range = range(
            $baseTrack->year - $this->parameters['tolerance'],
            $baseTrack->year + $this->parameters['tolerance']
        );

        $currentYear = (new \DateTime())->format('Y');

        $years = array_filter(
            $range,
            function ($year) use ($currentYear) {
                return $year <= $currentYear;
            }
        );

        unset($range, $currentYear);

        return [ '$in' => $years ];
    }
}
