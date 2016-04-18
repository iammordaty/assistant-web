<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\Provider as BaseProvider;
use Assistant\Module\Track;
use Assistant\Module\Common;

class Musly extends BaseProvider
{
    /**
     * {@inheritDoc}
     */
    const METADATA_FIELD = 'pathname';

    /**
     * @var array
     */
    private $similarityMapBase = [
        [ 'House', 'Tech House' ],
        [ 'House', 'Progressive House' ],
        [ 'House', 'Deep House' ],
        [ 'House', 'Electro House' ],
        [ 'House', 'Indie Dance' ],

        [ 'Deep House', 'Indie Dance' ],
        [ 'Deep House', 'Progressive House' ],
        [ 'Deep House', 'Tech House' ],

        [ 'Tech House', 'Techno' ],
        [ 'Tech House', 'Indie Dance' ],
        [ 'Tech House', 'Electro House' ],

        [ 'Techno', 'Hard Techno' ],
        [ 'Techno', 'Minimal' ],

        [ 'Trance', 'Progressive Trance' ],
        [ 'Trance', 'Hard Trance' ],
    ];

    /**
     * @var array|null
     */
    private $similarTracks = null;

    /**
     * {@inheritDoc}
     */
    public function getSimilarity(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack)
    {
        if ($this->similarTracks === null) {
            $this->similarTracks = (new Common\Extension\Backend\Client())->getSimilarTracks(
                $baseTrack,
                $this->getSimilarGenres($baseTrack),
                $this->getSimilarYears($baseTrack)
            );
        }

        $similarity = 0;

        foreach ($this->similarTracks as $similarTrack) {
            if ($comparedTrack->pathname === $similarTrack['pathname']) {
                $similarity = $similarTrack['similarity'];

                break;
            }
        }

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track\Model\Track $baseTrack)
    {
        unset($baseTrack);

        return [
            '$exists' => true
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function setup()
    {
        $this->similarityMap = [];

        $insert = function ($key, $value) {
            if (array_key_exists($key, $this->similarityMap) === false) {
                $this->similarityMap[$key] = [];
            }

            if (in_array($value, $this->similarityMap[$key]) === false) {
                $this->similarityMap[$key][] = $value;
            }
        };

        foreach ($this->similarityMapBase as $map) {
            $insert($map[0], $map[0]);
            $insert($map[1], $map[1]);

            $this->similarityMap[$map[0]][] = $map[1];
            $this->similarityMap[$map[1]][] = $map[0];

            unset($map);
        }

        unset($map, $insert);
    }

    /**
     * @param Track\Model\Track $track
     * @return array
     */
    private function getSimilarGenres(Track\Model\Track $track)
    {
        return isset($this->similarityMap[$track->genre]) ? $this->similarityMap[$track->genre] : [ $track->genre ];
    }

    /**
     * @param Track\Model\Track $track
     * @return array
     */
    private function getSimilarYears(Track\Model\Track $track)
    {
        $years = [
            $track->year - 1,
            $track->year,
        ];

        if ($track->year < ($currentYear = (new \DateTime())->format('Y'))) {
            $years[] = $track->year + 1;
        }

        return $years;
    }
}
