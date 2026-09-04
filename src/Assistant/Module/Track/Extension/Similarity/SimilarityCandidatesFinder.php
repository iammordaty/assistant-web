<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Search\Extension\Criteria\Not;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\CandidateProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\ProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Model\Track;

/** Wyszukuje utwory, które mogą zostać uznane za podobne do utworu bazowego */
final class SimilarityCandidatesFinder
{
    /**
     * @param TrackSearchService $trackSearchService
     * @param ProviderInterface[] $providers
     */
    public function __construct(
        private TrackSearchService $trackSearchService,
        private array $providers,
    ) {
    }

    /**
     * Zbiór kandydatów to suma dopasowania metadanych oraz utworów wskazanych przez dostawców
     * umiejących zgłosić własnych kandydatów. Kryteria w warstwie zapytań łączą się iloczynem,
     * więc sumy nie da się wyrazić jednym zapytaniem.
     *
     * @return Track[]
     */
    public function find(Track $baseTrack): array
    {
        $candidates = [];

        foreach ($this->getCandidateCriteria($baseTrack) as $criteria) {
            $result = $this->trackSearchService->search($criteria);

            foreach ($result->tracks as $candidate) {
                // guid jako klucz usuwa powtórzenia utworów obecnych w obu zbiorach
                $candidates[$candidate->getGuid()] = $candidate;
            }
        }

        unset($candidates[$baseTrack->getGuid()]);

        return array_values($candidates);
    }

    /** @return SearchCriteria[] */
    private function getCandidateCriteria(Track $baseTrack): array
    {
        $criteria = [ $this->getSimilarityCriteria($baseTrack) ];

        foreach ($this->providers as $provider) {
            if (!$provider instanceof CandidateProviderInterface) {
                continue;
            }

            $pathnames = $provider->getCandidatePathnames($baseTrack);

            if ($pathnames) {
                $criteria[] = new SearchCriteria(
                    guid: Not::equal($baseTrack->getGuid()),
                    pathname: $pathnames,
                );
            }
        }

        return $criteria;
    }

    /**
     * Zwraca kryteria, które muszą zostać spełnione, aby w trybie wyszukiwania
     * uznać utwór za podobny do podanego (i został pobrany z repozytorium)
     */
    private function getSimilarityCriteria(Track $baseTrack): SearchCriteria
    {
        $providerCriteria = [];

        foreach ($this->providers as $provider) {
            $providerCriteria[$provider::NAME] = $provider->getCriteria($baseTrack);
        }

        return new SearchCriteria(
            guid: Not::equal($baseTrack->getGuid()),
            bpm: $providerCriteria[Bpm::NAME] ?? null,
            genres: $providerCriteria[Genre::NAME] ?? null,
            initialKeys: $providerCriteria[MusicalKey::NAME] ?? null,
            years: $providerCriteria[Year::NAME] ?? null,
        );
    }
}
