<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\Provider as BaseProvider;
use Assistant\Module\Track\Model\Track;
use KeyTools\KeyTools;

class CamelotKeyCode extends BaseProvider
{
    /**
     * {@inheritDoc}
     */
    const METADATA_FIELD = 'initial_key';

    /**
     * {@inheritDoc}
     */
    public function getSimilarity(Track $baseTrack, Track $comparedTrack)
    {
        return $this->similarityMap[$baseTrack->initial_key][$comparedTrack->initial_key] ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track $baseTrack)
    {
        return [ '$in' => array_keys($this->similarityMap[$baseTrack->initial_key]) ];
    }

    /**
     * {@inheritDoc}
     */
    protected function setup()
    {
        $keyTools = new KeyTools([ 'notation' => KeyTools::NOTATION_CAMELOT_KEY ]);

        foreach (KeyTools::NOTATION_KEYS_CAMELOT_KEY as $keyCode) {
            $this->similarityMap[$keyCode] = [
                $keyCode => static::MAX_SIMILARITY_VALUE,
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
