<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Track\Model\Track;

final class Year extends AbstractProvider
{
    /** {@inheritDoc} */
    public const string NAME = 'Year';

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
        $baseYear = $baseTrack->getYear();
        $comparedYear = $comparedTrack->getYear();

        if ($baseYear === null || $comparedYear === null) {
            return 0;
        }

        $distance = abs($baseYear - $comparedYear);

        return $this->similarityMap[$distance] ?? 0;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): ?MinMaxInfo
    {
        $year = $baseTrack->getYear();

        // bez roku utworu bazowego okno nie ma środka, więc filtr roku jest pomijany
        if ($year === null) {
            return null;
        }

        // okno pokrywa się z nośnikiem mapy podobieństwa, więc filtr nie wpuszcza utworów,
        // którym dostawca przyznałby zero punktów
        $fromYear = $year - $this->parameters['tolerance'];
        $toYear = $year + $this->parameters['tolerance'];

        $minMaxInfo = MinMaxInfo::create([
            MinMaxInfo::GREATER_THAN_OR_EQUAL => $fromYear,
            MinMaxInfo::LESS_THAN_OR_EQUAL => $toYear,
        ]);

        return $minMaxInfo;
    }
}
