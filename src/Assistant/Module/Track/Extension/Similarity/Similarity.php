<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Search\Extension\Criteria\Not;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\CandidateProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\ProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Model\Track;

/**
 * Moduł podobieństwa
 *
 * @idea Być może należałoby wprowadzić klasę odpowiedzialną za listę podobnych utworów
 *       (na wzór SimilarTracksResultList), rozdzielając tym samym pobieranie z bazy od sortowania, mapowania, itp
 */
final class Similarity
{
    /** Lista dostępnych dostawców podobieństwa */
    public const array PROVIDERS = [
        Bpm::NAME,
        Genre::NAME,
        MusicalKey::NAME,
        Musly::NAME,
        Year::NAME,
    ];

    /**
     * @param TrackSearchService $trackSearchService
     * @param ProviderInterface[] $providers
     * @param array $providersWeights
     * @param int $minSimilarityValue
     * @param int $maxTracks
     */
    public function __construct(
        private TrackSearchService $trackSearchService,
        private array $providers,
        private array $providersWeights,
        private int $minSimilarityValue,
        private int $maxTracks,
    ) {
        if (count($this->providers) === 0) {
            throw new \RuntimeException('At least one similarity provider must be enabled');
        }

        $this->validateProviders();
    }

    /** Zwraca utwory podobne do podanego */
    public function getSimilarTracks(Track $baseTrack): array
    {
        // odrzuć wartości poniżej progu i ogranicz do zadanej wartości

        $similarTracks = array_filter(
            $this->getScoredCandidates($baseTrack),
            fn (SimilarTracks $similarTrack) => $similarTrack->getSimilarityValue() >= $this->minSimilarityValue
        );

        $similarTracks = array_slice($similarTracks, 0, $this->maxTracks);

        return $similarTracks;
    }

    /**
     * Zwraca wszystkich kandydatów z wyliczonym podobieństwem, posortowanych malejąco.
     * getSimilarTracks() zawęża tę listę progiem i limitem.
     *
     * @return SimilarTracks[]
     */
    private function getScoredCandidates(Track $baseTrack): array
    {
        $similarTracks = array_map(
            fn (Track $candidate) => new SimilarTracks(
                $baseTrack,
                $candidate,
                $this->getRawSimilarityValue($baseTrack, $candidate)
            ),
            $this->getCandidates($baseTrack)
        );

        return $this->sort($similarTracks);
    }

    /** Oblicza podobieństwo pomiędzy utworami */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        return (int) round($this->getRawSimilarityValue($baseTrack, $comparedTrack));
    }

    /**
     * Podobieństwo bez zaokrąglenia, żeby sortowanie nie opierało się na wartościach spłaszczonych
     * do liczb całkowitych. Zaokrąglenie należy do prezentacji.
     *
     * Mianownik liczony jest dla każdej pary osobno, wyłącznie z dostawców, którzy mieli dane.
     * Dzięki temu utwór z niepełnymi tagami nie jest karany za brak danych, a wynik pozostaje
     * w skali 0-100 niezależnie od tego, ilu dostawców jest włączonych.
     */
    private function getRawSimilarityValue(Track $baseTrack, Track $comparedTrack): float
    {
        $weightedSimilarity = 0.0;
        $maxWeightedSimilarity = 0.0;

        foreach ($this->providers as $provider) {
            $providerSimilarity = $provider->getSimilarityValue($baseTrack, $comparedTrack);

            if ($providerSimilarity === null) {
                continue;
            }

            $maxProviderSimilarity = $provider->getMaxSimilarityValue();
            $providerWeight = $this->providersWeights[$provider::NAME];

            // wartość poza zadeklarowanym zakresem nie może przesuwać wyniku poza skalę
            $providerSimilarity = min(max($providerSimilarity, 0), $maxProviderSimilarity);

            $weightedSimilarity += $providerSimilarity * $providerWeight;
            $maxWeightedSimilarity += $maxProviderSimilarity * $providerWeight;
        }

        if ($maxWeightedSimilarity <= 0.0) {
            // żaden dostawca nie miał danych o tej parze
            return 0.0;
        }

        return $weightedSimilarity * 100 / $maxWeightedSimilarity;
    }

    /**
     * Zbiór kandydatów to suma dopasowania metadanych oraz utworów wskazanych przez dostawców
     * potrafiących zgłosić własnych kandydatów. Kryteria w warstwie zapytań łączą się iloczynem,
     * więc sumy nie da się wyrazić jednym zapytaniem.
     *
     * @return Track[]
     */
    private function getCandidates(Track $baseTrack): array
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

    /** Sprawdza poprawność konfiguracji dostawców */
    private function validateProviders(): void
    {
        $providerNames = [];

        foreach ($this->providers as $provider) {
            $providerName = $provider->getName();

            if (!$providerName) {
                $message = sprintf('Provider class "%s" has invalid name (name can not be empty)', $provider::class);

                throw new \RuntimeException($message);
            }

            if (in_array($providerName, $providerNames)) {
                $message = sprintf('Provider class "%s" has duplicate name "%s"', $provider::class, $providerName);

                throw new \RuntimeException($message);
            }

            $providerNames[] = $providerName;

            if (!isset($this->providersWeights[$providerName])) {
                $message = sprintf('Weight not defined for provider "%s"', $providerName);

                throw new \RuntimeException($message);
            }

            unset($providerName, $provider);
        }
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

    /** Sortuje listę podobnych utworów */
    private function sort(array $result): array
    {
        usort($result, static function (SimilarTracks $first, SimilarTracks $second) {
            // podobieństwo malejąco

            $result = $first->getSimilarityValue() <=> $second->getSimilarityValue();

            if ($result !== 0) {
                return $result * -1;
            }

            // guid rosnąco

            $result = $first->getSecondTrack()->getGuid() <=> $second->getSecondTrack()->getGuid();

            if ($result !== 0) {
                return $result;
            }

            return 0;
        });

        return $result;
    }
}
