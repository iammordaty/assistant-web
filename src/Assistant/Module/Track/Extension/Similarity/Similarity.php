<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Common\Storage\Regex;
use Assistant\Module\Search\Extension\SearchCriteria;
use Assistant\Module\Search\Extension\TrackSearchService;
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
    public const PROVIDERS = [
        Bpm::NAME,
        Genre::NAME,
        MusicalKey::NAME,
        Musly::NAME,
        Year::NAME,
    ];

    /** Liczba dostępnych dostawców */
    private int $providersCount;

    /** Maksymalna, uwzględniająca wagi dostawców, wartość podobieństwa, która może zostać zwrócona */
    private int $maxSimilarityValue;

    /**
     * @param TrackSearchService $searchService
     * @param ProviderInterface[] $providers
     * @param array $providersWeights
     * @param int $minSimilarityValue
     * @param int $maxTracks
     */
    public function __construct(
        private TrackSearchService $searchService,
        private array $providers,
        private array $providersWeights,
        private int $minSimilarityValue,
        private int $maxTracks,
    ) {
        $this->providersCount = count($this->providers);

        if ($this->providersCount === 0) {
            throw new \RuntimeException('At least one similarity provider must be enabled');
        }

        $this->setup();
    }

    /** Zwraca utwory podobne do podanego */
    public function getSimilarTracks(Track $baseTrack): array
    {
        $criteria = $this->getSimilarityCriteria($baseTrack);
        $similarTracks = $this->searchService->findBy($criteria);

        $similarTracks = array_map(
            fn (Track $similarTrack) => new SimilarTracks(
                $baseTrack,
                $similarTrack,
                $this->getSimilarityValue($baseTrack, $similarTrack)
            ),
            iterator_to_array($similarTracks)
        );

        // posortuj wg. podoieństwa

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
        $similarity = 0;

        foreach ($this->providers as $provider) {
            $providerSimilarity = $provider->getSimilarityValue($baseTrack, $comparedTrack);

            $providerName = $provider::NAME;
            $providerWeight = $this->providersWeights[$providerName];

            $similarity += $providerSimilarity * $providerWeight;
        }

        return round(($similarity / $this->providersCount * 100) / $this->maxSimilarityValue);
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

        $maxSimilarityValue = array_reduce($this->providers, fn ($similarityValue, ProviderInterface $provider) => (
            $similarityValue + ($provider->getMaxSimilarityValue() * $this->providersWeights[$provider::NAME])
        ), 0);

        $this->maxSimilarityValue = $maxSimilarityValue / $this->providersCount;
    }

    /**
     * Zwraca kryteria, które muszą zostać spełnione, aby w trybie wyszukiwania
     * uznać utwór za podobny do podanego (i został pobrany z repozytorium)
     */
    private function getSimilarityCriteria(Track $baseTrack): SearchCriteria
    {
        $providerCriteria = [];

        // To jest krok pośredni, aby zejść z wykorzystywanej metody w repozytorium i przełączyć się na SearchCriteria.
        // @idea: Najlepiej byłoby, aby provider w getCriteria zwracały SearchCriteria lub null,
        //        a SearchCriteriaFacade łączyłby je w jeden.
        foreach ($this->providers as $provider) {
            $providerCriteria[$provider::NAME] = $provider->getCriteria($baseTrack);
        }

        $searchCriteria = new SearchCriteria(
            guid: Regex::create(sprintf('^(?!%s$)', $baseTrack->getGuid())), // zwykły $ne byłby lepszy
            bpm: $providerCriteria[Bpm::NAME] ?? null,
            genres: $providerCriteria[Genre::NAME] ?? null,
            initialKeys: $providerCriteria[MusicalKey::NAME] ?? null,
            years: $providerCriteria[Year::NAME] ?? null,
        );

        return $searchCriteria;
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
