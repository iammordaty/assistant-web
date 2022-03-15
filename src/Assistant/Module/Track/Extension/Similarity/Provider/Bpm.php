<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Search\Extension\MinMaxInfo;
use Assistant\Module\Track\Model\Track;

final class Bpm extends AbstractProvider
{
    /** {@inheritDoc} */
    public const NAME = 'BPM';

    /** {@inheritDoc} */
    protected array $similarityMap = [
        0 => self::MAX_SIMILARITY_VALUE,
        1 => 98,
        2 => 93,
        3 => 70,
        4 => 60,
        5 => 30,
    ];

    public function __construct(private array $parameters)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        $distance = (int) round(abs($baseTrack->getBpm() - $comparedTrack->getBpm()));
        $similarity = $this->similarityMap[$distance] ?? 0;

        // echo $baseTrack->getBpm(), ' vs. ', $comparedTrack->getBpm(), ' = ', $similarity, " ($distance)", PHP_EOL;

        return $similarity;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): MinMaxInfo
    {
        $roundedBpm = round($baseTrack->getBpm());

        $minBpm = $roundedBpm - $this->parameters['tolerance'];
        $maxBpm = $roundedBpm + $this->parameters['tolerance'];

        $minMaxInfo = MinMaxInfo::create([
            MinMaxInfo::GREATER_THAN_OR_EQUAL => $minBpm,
            MinMaxInfo::LESS_THAN_OR_EQUAL => $maxBpm,
        ]);

        return $minMaxInfo;
    }
}
