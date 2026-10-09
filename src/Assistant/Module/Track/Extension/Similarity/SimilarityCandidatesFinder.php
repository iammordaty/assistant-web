<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Search\Extension\Criteria\Not;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\CandidateProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\IndexedPeriod;
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
     * @param bool $favoritesOnly AI: tryb „Pewniak” zawęża kandydatów do ulubionych już w zapytaniu,
     *        a nie po obcięciu wyników do limitu, i dotyczy to również kandydatów wskazanych przez dostawców
     */
    public function __construct(
        private TrackSearchService $trackSearchService,
        private array $providers,
        private bool $favoritesOnly = false,
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
                    isFavorite: $this->favoritesOnly ?: null,
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

        $createCriteria = fn (?MinMaxInfo $indexedDates) => new SearchCriteria(
            guid: Not::equal($baseTrack->getGuid()),
            bpm: $providerCriteria[Bpm::NAME] ?? null,
            genres: $providerCriteria[Genre::NAME] ?? null,
            initialKeys: $providerCriteria[MusicalKey::NAME] ?? null,
            years: $providerCriteria[Year::NAME] ?? null,
            isFavorite: $this->favoritesOnly ?: null,
            indexedDates: $indexedDates,
        );

        $indexedPeriod = $this->getIndexedPeriodProvider();

        if (!$indexedPeriod || !$baseTrack->getIndexedDate()) {
            return $createCriteria(null);
        }

        // AI: kryteria łączą się iloczynem, więc o poszerzeniu okna okresu decyduje liczba utworów
        // spełniających wszystkie kryteria naraz; dlatego okno jest dobierane tutaj, a nie w dostawcy
        foreach ($indexedPeriod->getTolerances() as $tolerance) {
            $criteria = $createCriteria($indexedPeriod->getCriteriaWithin($baseTrack, $tolerance));

            if ($this->trackSearchService->count($criteria) >= $indexedPeriod->getMinTracks()) {
                break;
            }
        }

        return $criteria;
    }

    private function getIndexedPeriodProvider(): ?IndexedPeriod
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof IndexedPeriod) {
                return $provider;
            }
        }

        return null;
    }
}
