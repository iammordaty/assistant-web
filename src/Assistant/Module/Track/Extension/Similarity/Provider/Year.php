<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Search\Extension\MinMaxInfo;
use Assistant\Module\Track\Model\Track;

final class Year extends AbstractProvider
{
    /** {@inheritDoc} */
    public const NAME = 'Year';

    /** {@inheritDoc} */
    protected array $similarityMap = [
        0 => self::MAX_SIMILARITY_VALUE,
        1 => 98,
        2 => 90,
        3 => 70,
        4 => 40,
        5 => 20,
    ];

    public function __construct(private array $parameters)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        if ($comparedTrack->getYear() === $baseTrack->getYear()) {
            return self::MAX_SIMILARITY_VALUE;
        }

        $distance = abs($baseTrack->getYear() - $comparedTrack->getYear());
        $similarity = $this->similarityMap[$distance] ?? 0;

        // echo $baseTrack->getYear(), ' vs. ', $comparedTrack->getYear(), ' = ', $similarity, " ($distance)", PHP_EOL;

        return $similarity;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): MinMaxInfo
    {
        $fromYear = $baseTrack->getYear() - $this->parameters['tolerance'];

        $currentYear = (new \DateTime())->format('Y');
        $toYear = (int) max($currentYear, $baseTrack->getYear() + $this->parameters['tolerance']);

        $minMaxInfo = MinMaxInfo::create([
            MinMaxInfo::GREATER_THAN_OR_EQUAL => $fromYear,
            MinMaxInfo::LESS_THAN_OR_EQUAL => $toYear,
        ]);

        return $minMaxInfo;
    }
}
