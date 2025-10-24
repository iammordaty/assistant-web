<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;
use KeyTools\KeyTools;

final class MusicalKey extends AbstractProvider
{
    /** {@inheritDoc} */
    public const NAME = 'MusicalKey';

    public function __construct()
    {
        $this->setup();
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        $similarity = $this->similarityMap[$baseTrack->getInitialKey()][$comparedTrack->getInitialKey()] ?? 0;

        return $similarity;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): ?array
    {
        $initialKeySimilarityMap = $this->similarityMap[$baseTrack->getInitialKey()] ?? null;

        if (!$initialKeySimilarityMap) {
            return null;
        }

        $similarKeys = array_keys($initialKeySimilarityMap);

        return $similarKeys;
    }

    /** Przygotowuje dostawcę do użycia */
    private function setup(): void
    {
        $keyTools = KeyTools::fromNotation(KeyTools::NOTATION_CAMELOT_KEY);

        foreach (KeyTools::NOTATION_KEYS_CAMELOT_KEY as $keyCode) {
            $this->similarityMap[$keyCode] = [
                $keyTools->noChange($keyCode) => self::MAX_SIMILARITY_VALUE,
                $keyTools->relativeMinorToMajor($keyCode) => 95,
                $keyTools->perfectFourth($keyCode) => 95,
                $keyTools->perfectFifth($keyCode) => 95,
                $keyTools->dominantRelative($keyCode) => 65,
                $keyTools->minorThird($keyCode) => 65,
                $keyTools->wholeStep($keyCode) => 65,
                $keyTools->halfStep($keyCode) => 65,
            ];
        }
    }
}
