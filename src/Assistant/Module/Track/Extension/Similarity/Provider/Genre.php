<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\Provider as BaseProvider;
use Assistant\Module\Track;

class Genre extends BaseProvider
{
    /**
     * {@inheritDoc}
     */
    const METADATA_FIELD = 'genre';

    /**
     * @var array
     */
    private $similarityMapBase = [
        [ 'House', 'Tech House', 90 ],
        [ 'House', 'Progressive House', 90 ],
        [ 'House', 'Deep House', 90 ],
        [ 'House', 'Electro House', 80 ],
        [ 'House', 'Indie Dance', 75 ],
        [ 'House', 'Techno', 60 ],
        [ 'House', 'Electronic', 50 ],

        [ 'Deep House', 'Indie Dance', 95 ],
        [ 'Deep House', 'Progressive House', 85 ],
        [ 'Deep House', 'Tech House', 75 ],
        [ 'Deep House', 'Techno', 50 ],
        [ 'Deep House', 'Electro House', 50 ],
        [ 'Deep House', 'Electronic', 50 ],

        [ 'Tech House', 'Techno', 90 ],
        [ 'Tech House', 'Indie Dance', 85 ],
        [ 'Tech House', 'Electro House', 65 ],
        [ 'Tech House', 'Progressive House', 60 ],
        [ 'Tech House', 'Electronic', 50 ],

        [ 'Techno', 'Hard Techno', 85 ],
        [ 'Techno', 'Minimal', 85 ],
        [ 'Techno', 'Indie Dance', 50 ],
        [ 'Techno', 'Electronic', 50 ],

        [ 'Trance', 'Progressive Trance', 90 ],
        [ 'Trance', 'Hard Trance', 85 ],
        [ 'Trance', 'Electronic', 50 ],
    ];

    /**
     * {@inheritDoc}
     */
    public function getSimilarity(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack)
    {
        if ($comparedTrack->genre === $baseTrack->genre) {
            return static::MAX_SIMILARITY_VALUE;
        }

        $similarity = 0;

        foreach ($this->similarityMap as $map) {
            if ($baseTrack->genre === $map[0] && $comparedTrack->genre === $map[1]) {
                $similarity = $map[2];

                break;
            }
        }

        // echo $baseTrack->genre, ' vs. ', $comparedTrack->genre, ' = ', $similarity, PHP_EOL;

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track\Model\Track $baseTrack)
    {
        $genres = [ ];

        foreach ($this->similarityMap as $map) {
            if ($baseTrack->genre === $map[0]) {
                $genres[] = $map[1];
            }
        }

        // $baseTrack->genre jest innym, niewymienionym gatunkiem (np. hardstyle, hard house)
        if (empty($genres)) {
            $genres[] = $baseTrack->genre;
        }

        return [
            '$in' => $genres
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function setup()
    {
        $this->similarityMap = [ ];

        foreach ($this->similarityMapBase as $map) {
            $this->similarityMap[] = [
                $map[0],
                $map[0],
                static::MAX_SIMILARITY_VALUE,
            ];

            $this->similarityMap[] = [
                $map[0],
                $map[1],
                $map[2],
            ];

            $this->similarityMap[] = [
                $map[1],
                $map[0],
                $map[2],
            ];
        }
    }
}
