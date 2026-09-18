<?php

namespace Assistant\Module\Track\Extension\Similarity;

/** Porządkuje listę podobnych utworów */
final class SimilarityCandidatesSorter
{
    /**
     * Sortuje listę podobnych utworów: podobieństwo malejąco, rok malejąco, guid rosnąco.
     * Ostatnie kryterium zapewnia powtarzalną kolejność utworów nierozróżnialnych wcześniejszymi.
     *
     * @param SimilarityCandidateTrack[] $similarTracks
     * @return SimilarityCandidateTrack[]
     */
    public function sort(array $similarTracks): array
    {
        usort($similarTracks, static fn (SimilarityCandidateTrack $first, SimilarityCandidateTrack $second) => (
            $second->getSimilarityValue() <=> $first->getSimilarityValue()
                ?: $second->getTrack()->getYear() <=> $first->getTrack()->getYear()
                ?: $first->getTrack()->getGuid() <=> $second->getTrack()->getGuid()
        ));

        return $similarTracks;
    }
}
