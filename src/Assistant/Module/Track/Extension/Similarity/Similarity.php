<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Publisher;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Model\Track;

/** Moduł podobieństwa */
final class Similarity
{
    /** Lista dostępnych dostawców podobieństwa */
    public const array PROVIDERS = [
        Bpm::NAME,
        Genre::NAME,
        MusicalKey::NAME,
        Musly::NAME,
        Publisher::NAME,
        Year::NAME,
    ];

    public function __construct(
        private SimilarityCandidatesFinder $candidateFinder,
        private SimilarityCalculator $similarityCalculator,
        private SimilarityCandidatesSorter $sorter,
        private int $minSimilarityValue,
        private int $maxTracks,
    ) {
    }

    /** Zwraca utwory podobne do podanego */
    public function getSimilarTracks(Track $baseTrack): array
    {
        $candidates = $this->candidateFinder->find($baseTrack);

        $toScoredTrack = fn (Track $candidate) => new SimilarityCandidateTrack(
            $candidate,
            $this->similarityCalculator->calculate($baseTrack, $candidate),
        );

        $similarTracks = array_map($toScoredTrack, $candidates);

        $similarTracks = $this->sorter->sort($similarTracks);

        $similarTracks = array_filter(
            $similarTracks,
            fn (SimilarityCandidateTrack $scoredTrack) => $scoredTrack->getSimilarityValue() >= $this->minSimilarityValue
        );

        return array_slice(array_values($similarTracks), 0, $this->maxTracks);
    }

    /** Oblicza podobieństwo pomiędzy utworami */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        return $this->similarityCalculator->calculate($baseTrack, $comparedTrack);
    }
}
