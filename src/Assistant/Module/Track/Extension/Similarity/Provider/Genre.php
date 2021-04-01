<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

class Genre extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public const NAME = 'Genre';

    /**
     * {@inheritDoc}
     */
    protected const SIMILARITY_FIELD = 'genre';

    /**
     * @var array
     */
    private array $similarityMapBase = [
        ['House', 'Tech House', 90],
        ['House', 'Progressive House', 90],
        ['House', 'Deep House', 90],
        ['House', 'Electro House', 80],
        ['House', 'Indie Dance', 75],
        ['House', 'Techno', 60],
        ['House', 'Club', 60],
        ['House', 'Electronic', 50],

        ['Deep House', 'Indie Dance', 95],
        ['Deep House', 'Progressive House', 85],
        ['Deep House', 'Tech House', 75],
        ['Deep House', 'Techno', 55],
        ['Deep House', 'Electronic', 50],
        ['Deep House', 'Electro House', 50],

        ['Tech House', 'Techno', 90],
        ['Tech House', 'Indie Dance', 70],
        ['Tech House', 'Electro House', 65],
        ['Tech House', 'Progressive House', 60],
        ['Tech House', 'Electronic', 50],

        ['Techno', 'Hard Techno', 85],
        ['Techno', 'Hard Trance', 85],
        ['Trance', 'Hard House', 85],
        ['Techno', 'Minimal', 85],
        ['Techno', 'Deep House', 55],
        ['Techno', 'Indie Dance', 50],
        ['Techno', 'Electronic', 50],

        ['Trance', 'Progressive Trance', 90],
        ['Trance', 'Hard Trance', 85],
        ['Trance', 'Progressive House', 60],
        ['Trance', 'Electronic', 50],
    ];

    public function __construct()
    {
        $this->setup();
    }

    /**
     * {@inheritDoc}
     */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        if ($comparedTrack->getGenre() === $baseTrack->getGenre()) {
            return static::MAX_SIMILARITY_VALUE;
        }

        $similarity = 0;

        foreach ($this->similarityMap as $map) {
            [ $baseGenre, $comparedGenre, $genreSimilarity ] = $map;

            if ($baseTrack->getGenre() === $baseGenre && $comparedTrack->getGenre() === $comparedGenre) {
                $similarity = $genreSimilarity;

                break;
            }
        }

        // echo $baseTrack->$this->getGenre(), ' vs. ', $comparedTrack->$this->getGenre(), ' = ', $similarity, PHP_EOL;

        return $similarity;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track $baseTrack): array
    {
        $genres = [];

        foreach ($this->similarityMap as $map) {
            if ($baseTrack->getGenre() === $map[0]) {
                $genres[] = $map[1];
            }
        }

        // $baseTrack->$this->getGenre() jest innym, niewymienionym gatunkiem (np. hardstyle, hard house)
        if (empty($genres)) {
            $genres[] = $baseTrack->getGenre();
        }

        return [
            '$in' => $genres
        ];
    }

    /**
     * Przygotowuje dostawcę do użycia
     */
    private function setup(): void
    {
        $this->similarityMap = [];

        foreach ($this->similarityMapBase as $map) {
            [ $baseGenre, $comparedGenre, $similarity ] = $map;

            $max = [
                $baseGenre,
                $baseGenre,
                static::MAX_SIMILARITY_VALUE,
            ];

            if (!in_array($max, $this->similarityMap, true)) {
                $this->similarityMap[] = $max;
            }

            $max = [
                $comparedGenre,
                $comparedGenre,
                static::MAX_SIMILARITY_VALUE,
            ];

            if (!in_array($max, $this->similarityMap, true)) {
                $this->similarityMap[] = $max;
            }

            $this->similarityMap[] = [
                $baseGenre,
                $comparedGenre,
                $similarity,
            ];

            $this->similarityMap[] = [
                $comparedGenre,
                $baseGenre,
                $similarity,
            ];

            unset($map, $baseGenre, $comparedGenre, $similarity, $max);
        }
    }
}
