<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Search\Extension\Criteria\Not;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
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

    /** Maksymalna, uwzględniająca wagi dostawców, wartość podobieństwa, która może zostać zwrócona */
    private float $maxSimilarityValue;

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

        $this->setup();
    }

    /** Zwraca utwory podobne do podanego */
    public function getSimilarTracks(Track $baseTrack): array
    {
        $criteria = $this->getSimilarityCriteria($baseTrack);
        $result = $this->trackSearchService->search($criteria);

        $similarTracks = array_map(
            fn (Track $similarTrack) => new SimilarTracks(
                $baseTrack,
                $similarTrack,
                $this->getSimilarityValue($baseTrack, $similarTrack)
            ),
            iterator_to_array($result->tracks)
        );

        // posortuj wg podobieństwa

        $similarTracks = $this->sort($similarTracks);

        // i odrzuć wartości poniżej progu i ogranicz do zadanej wartości

        $similarTracks = array_filter(
            $similarTracks,
            fn (SimilarTracks $similarTrack) => $similarTrack->getSimilarityValue() >= $this->minSimilarityValue
        );

        $similarTracks = array_slice($similarTracks, 0, $this->maxTracks);

        return $similarTracks;
    }

    /** Oblicza podobieństwo pomiędzy utworami */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        $similarity = 0.0;

        $providerSimilarityValues = $this->getProviderSimilarityValues($baseTrack, $comparedTrack);

        foreach ($providerSimilarityValues as $providerName => $providerSimilarity) {
            $similarity += $providerSimilarity * $this->providersWeights[$providerName];
        }

        return (int) round($similarity * 100 / $this->maxSimilarityValue);
    }

    /**
     * Zwraca wartości zwrócone przez poszczególnych dostawców, bez uwzględnienia wag.
     * Służy diagnostyce rozkładu wartości (task track:similarity-report).
     *
     * @fixme: To nie powinno być tutaj, wynieść do innej klasy (typowo diagnostycznej, albo anonimowej)
     *         lub włączyć w skład taska jako metoda prywatna.
     *
     * @return array<string, int>
     */
    public function getProviderSimilarityValues(Track $baseTrack, Track $comparedTrack): array
    {
        $similarityValues = [];

        foreach ($this->providers as $provider) {
            $similarityValues[$provider::NAME] = $provider->getSimilarityValue($baseTrack, $comparedTrack);
        }

        return $similarityValues;
    }

    /** Przygotowuje moduł podobieństwa do użycia */
    private function setup(): void
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

        $this->maxSimilarityValue = array_reduce(
            $this->providers,
            fn (float $maxSimilarityValue, ProviderInterface $provider) => (
                $maxSimilarityValue + ($provider->getMaxSimilarityValue() * $this->providersWeights[$provider::NAME])
            ),
            0.0,
        );
    }

    /**
     * Zwraca kryteria, które muszą zostać spełnione, aby w trybie wyszukiwania
     * uznać utwór za podobny do podanego (i został pobrany z repozytorium)
     *
     * @fixme: To nie powinna być publiczna metoda. Podobnie jak getProviderSimilarityValues wynieść do innej klasy,
     *         lub włączyć w skład taska jako metoda prywatna.
     */
    public function getSimilarityCriteria(Track $baseTrack): SearchCriteria
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

            // rok malejąco

            $result = $first->getSecondTrack()->getYear() <=> $second->getSecondTrack()->getYear();

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
