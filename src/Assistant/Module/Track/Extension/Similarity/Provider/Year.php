<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

class Year extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public const NAME = 'Year';

    /**
     * {@inheritDoc}
     */
    protected const SIMILARITY_FIELD = 'year';

    /**
     * {@inheritDoc}
     */
    protected array $similarityMap = [
        0 => self::MAX_SIMILARITY_VALUE,
        1 => 98,
        2 => 90,
        3 => 70,
        4 => 40,
        5 => 20,
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
        if ($comparedTrack->getYear() === $baseTrack->getYear()) {
            return static::MAX_SIMILARITY_VALUE;
        }

        $distance = abs($baseTrack->getYear() - $comparedTrack->getYear());
        $similarity = $this->similarityMap[$distance] ?? 0;

        // echo $baseTrack->$this->getYear(), ' vs. ', $comparedTrack->$this->getYear(), ' = ', $similarity, " ($distance)", PHP_EOL;

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track $baseTrack): array
    {
        $range = range(
            $baseTrack->getYear() - $this->parameters['tolerance'],
            $baseTrack->getYear() + $this->parameters['tolerance']
        );

        $currentYear = (new \DateTime())->format('Y');

        $years = array_filter(
            $range,
            static fn($year) => $year <= $currentYear
        );

        unset($range, $currentYear);

        return [ '$in' => $years ];
    }
}
