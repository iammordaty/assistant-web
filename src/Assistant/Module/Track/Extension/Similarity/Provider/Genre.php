<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

final class Genre extends AbstractProvider
{
    /** {@inheritDoc} */
    public const NAME = 'Genre';

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

        ['Progressive House', 'Indie Dance', 80],
        ['Progressive House', 'Electro House', 65],
        ['Progressive House', 'Electronic', 60],
        ['Progressive House', 'Techno', 50],
        ['Progressive House', 'Progressive Trance', 40],

        ['Tech House', 'Techno', 90],
        ['Tech House', 'Indie Dance', 70],
        ['Tech House', 'Electro House', 65],
        ['Tech House', 'Progressive House', 60],
        ['Tech House', 'Electronic', 50],

        ['Techno', 'Hard Techno', 85],
        ['Techno', 'Hard Trance', 85],
        ['Trance', 'Hard House', 85],
        ['Techno', 'Minimal', 85],
        ['Techno', 'Electronic', 75],
        ['Techno', 'Deep House', 55],
        ['Techno', 'Indie Dance', 50],

        ['Trance', 'Progressive Trance', 90],
        ['Trance', 'Hard Trance', 85],
        ['Trance', 'Progressive House', 60],
        ['Trance', 'Electronic', 50],
    ];

    public function __construct()
    {
        $this->setup();
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        if ($comparedTrack->getGenre() === $baseTrack->getGenre()) {
            return self::MAX_SIMILARITY_VALUE;
        }

        $similarity = 0;

        foreach ($this->similarityMap as $map) {
            [ $baseGenre, $comparedGenre, $genreSimilarity ] = $map;

            if ($baseTrack->getGenre() === $baseGenre && $comparedTrack->getGenre() === $comparedGenre) {
                $similarity = $genreSimilarity;

                break;
            }
        }

        // echo $baseTrack->getGenre(), ' vs. ', $comparedTrack->getGenre(), ' = ', $similarity, PHP_EOL;

        return $similarity;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): ?array
    {
        $genres = [];

        foreach ($this->similarityMap as $map) {
            if ($baseTrack->getGenre() === $map[0]) {
                $genres[] = $map[1];
            }
        }

        // $baseTrack->getGenre() jest innym, niewymienionym gatunkiem (np. HardStyle, Hard-House),
        // więc rezygnujemy z tego filtra, bo uniemożliwia to zwrócenie podobnych utworów.
        if (empty($genres)) {
            return null;
        }

        return $genres;
    }

    /** Przygotowuje dostawcę do użycia */
    private function setup(): void
    {
        $this->similarityMap = [];

        foreach ($this->similarityMapBase as $map) {
            [ $baseGenre, $comparedGenre, $similarity ] = $map;

            $max = [
                $baseGenre,
                $baseGenre,
                self::MAX_SIMILARITY_VALUE,
            ];

            if (!in_array($max, $this->similarityMap, true)) {
                $this->similarityMap[] = $max;
            }

            $max = [
                $comparedGenre,
                $comparedGenre,
                self::MAX_SIMILARITY_VALUE,
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
