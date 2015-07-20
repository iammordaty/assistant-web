<?php
namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\Provider as BaseProvider;
use Assistant\Module\Common;
use Assistant\Module\Track;

class CamelotKeyCode extends BaseProvider
{
    /**
     * {@inheritDoc}
     */
    public function getSimilarity(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack)
    {
        return isset($this->similarityMap[$baseTrack->initial_key][$comparedTrack->initial_key])
            ? $this->similarityMap[$baseTrack->initial_key][$comparedTrack->initial_key]
            : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track\Model\Track $baseTrack)
    {
        return [ '$in' => array_keys($this->similarityMap[$baseTrack->initial_key]) ];
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadataField()
    {
        return 'initial_key';
    }

    /**
     * {@inheritDoc}
     */
    protected function setup()
    {
        $keyTools = new Common\Extension\KeyTools();

        foreach ($keyTools->camelotCode as $keyCode) {
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
