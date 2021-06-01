<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;
use KeyTools\KeyTools;

final class MusicalKey extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public const NAME = 'MusicalKey';

    /**
     * {@inheritDoc}
     */
    protected const SIMILARITY_FIELD = 'initial_key';

    public function __construct()
    {
        $this->setup();
    }

    /**
     * {@inheritDoc}
     */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        $similarity = $this->similarityMap[$baseTrack->getInitialKey()][$comparedTrack->getInitialKey()] ?? 0;

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track $baseTrack): array
    {
        $similarKeys = array_keys($this->similarityMap[$baseTrack->getInitialKey()]);

        return [ '$in' => $similarKeys ];
    }

    /**
     * Przygotowuje dostawcę do użycia
     */
    private function setup(): void
    {
        $keyTools = KeyTools::fromNotation(KeyTools::NOTATION_CAMELOT_KEY);

        foreach (KeyTools::NOTATION_KEYS_CAMELOT_KEY as $keyCode) {
            $this->similarityMap[$keyCode] = [
                $keyCode => self::MAX_SIMILARITY_VALUE,
                $keyTools->perfectFourth($keyCode) => 95,
                $keyTools->perfectFifth($keyCode) => 95,
                $keyTools->dominantRelative($keyCode) => 90,
                $keyTools->minorThird($keyCode) => 80,
                $keyTools->relativeMinorToMajor($keyCode) => 80,
                $keyTools->wholeStep($keyCode) => 65,
                $keyTools->halfStep($keyCode) => 55,
            ];
        }
    }
}
