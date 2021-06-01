<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

final class Bpm extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public const NAME = 'BPM';

    /**
     * {@inheritDoc}
     */
    protected const SIMILARITY_FIELD = 'bpm';

    /**
     * {@inheritDoc}
     */
    protected array $similarityMap = [
        0 => self::MAX_SIMILARITY_VALUE,
        1 => 98,
        2 => 93,
        3 => 70,
        4 => 60,
        5 => 30,
    ];

    private array $parameters;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * {@inheritDoc}
     */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        $distance = (int) round(abs($baseTrack->getBpm() - $comparedTrack->getBpm()));
        $similarity = $this->similarityMap[$distance] ?? 0;

        // echo $baseTrack->getBpm(), ' vs. ', $comparedTrack->getBpm(), ' = ', $similarity, " ($distance)", PHP_EOL;

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track $baseTrack): array
    {
        $criteria = [
            '$gte' => round($baseTrack->getBpm()) - $this->parameters['tolerance'],
            '$lte' => round($baseTrack->getBpm()) + $this->parameters['tolerance'],
        ];

        return $criteria;
    }
}
