<?php
// skala durowa, major - wesoła
// skala molowa, minorowa - smutna

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\Provider as BaseProvider;
use Assistant\Module\Track;
use Assistant\Lib\KeyTools;

/**
 * Sprawdzić:
 *
 * https://github.com/PkerUNO/Crater
 * https://github.com/tfriedel/trackanalyzer/
 */
class CamelotKeyCode extends BaseProvider
{
    /*
     * X oznacza major, Xm - minor
     * b (bemol) - flat,
     * bez b - sharp - krzyżyk
     */

    /**
     * {@inheritDoc}
     */
    public function getSimilarity(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack)
    {
        if ($baseTrack->initial_key === $comparedTrack->initial_key) {
            return static::MAX_SIMILARITY_VALUE;
        }

        if (in_array($comparedTrack->initial_key, $this->similarityMap[$baseTrack->initial_key])) {
            return 90;
        }

        $baseTrackScale = substr($baseTrack->initial_key, -1);
        $comparedTrackScale = substr($comparedTrack->initial_key, -1);

        if ($baseTrackScale !== $comparedTrackScale) {
            return 0;
        }

        $similarity = static::MAX_SIMILARITY_VALUE - 30; // kara za brak podobieństwa

        $baseTrackCode = (int) rtrim($baseTrack->initial_key, 'AB');
        $comparedTrackCode = (int) rtrim($comparedTrack->initial_key, 'AB');

        if ($comparedTrackCode > $baseTrackCode) {
            $distance = $comparedTrackCode - $baseTrackCode;
        } else {
            $distance = $baseTrackCode - $comparedTrackCode;
        }

        $similarity -= $distance * $distance * 2;

        if ($similarity < 0) {
            $similarity = 0;
        }

        // echo $baseTrack->initial_key, ' vs. ', $comparedTrack->initial_key, ' = ', $similarity, " ($distance)", PHP_EOL;

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track\Model\Track $baseTrack)
    {
        return [ '$in' => $this->similarityMap[$baseTrack->initial_key] ];
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
        $keyTools = new KeyTools();

        foreach ($keyTools->camelotCode as $keyCode) {
            $this->similarityMap[$keyCode] = [
                $keyCode,
                $keyTools->perfectFourth($keyCode),
                $keyTools->perfectFifth($keyCode),
                $keyTools->relativeMinorToMajor($keyCode),
                $keyTools->minorThird($keyCode),
                $keyTools->halfStep($keyCode),
                $keyTools->wholeStep($keyCode),
                $keyTools->dominantRelative($keyCode),
            ];
        }
    }
}
