<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\ProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Extension\SimilarTracksVO as SimilarTrack;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;

/**
 * Moduł podobieństwa
 */
class Similarity
{
    private TrackRepository $repository;

    /**
     * Lista dostępnych dostawców podobieństwa
     *
     * @var array
     */
    private array $availableProviders = [
        Bpm::class,
        Genre::class,
        MusicalKey::class,
        Musly::class,
        Year::class,
    ];

    /**
     * Lista obiektów dostawców podobieństwa
     *
     * @see setup()
     * @var ProviderInterface[]
     */
    private array $providers = [];

    /**
     * Wagi dostawców podobieństwa
     *
     * @var array
     */
    private array $providerWeights = [];

    /**
     * Liczba dostępnych dostawców
     *
     * @see $providers
     * @var int
     */
    private int $providersCount;

    /**
     * Parametry modułu
     *
     * @var array
     */
    private array $parameters;

    /**
     * Maksymalna, uwzględniająca wagi dostawców, wartość podobieństwa, która może zostać zwrócona
     *
     * @var int
     */
    private int $maxSimilarityValue;

    public function __construct(TrackRepository $repository, array $parameters)
    {
        $this->repository = $repository;
        $this->parameters = $parameters;

        $this->setup();
    }

    /**
     * Zwraca utwory podobne do podanego
     *
     * @param Track $baseTrack
     * @return array
     */
    public function getSimilarTracks(Track $baseTrack): array
    {
        $criteria = $this->getSimilarityCriteria($baseTrack);

        // @fixme Przerzucić do repozytorium jako wyspecjalizowana metoda. Do przemyślenia jak to będzie najlepiej.

        $similarTracks = $this->repository->findBy($criteria);

        // @todo: być może foreach będzie bardziej czytelny niż map, slice i filter

        $similarTracks = array_map(
            fn(Track $similarTrack) => new SimilarTrack(
                $baseTrack,
                $similarTrack,
                $this->getSimilarityValue($baseTrack, $similarTrack)
            ),
            iterator_to_array($similarTracks)
        );

        // odrzuć wartości poniżej progu

        $similarityValueLimit = $this->parameters['limit']['value'];

        $similarTracks = array_filter(
            $similarTracks,
            static fn(SimilarTrack $similarTrack) => $similarTrack->getSimilarityValue() > $similarityValueLimit
        );

        //  odrzuć nadmiarowe

        $tracksLimit = $this->parameters['limit']['tracks'];
        $similarTracks = array_slice($similarTracks, 0, $tracksLimit);

        return $this->sort($similarTracks);
    }

    /**
     * Oblicza podobieństwo pomiędzy utworami
     *
     * @param Track $baseTrack
     * @param Track $comparedTrack
     * @return int
     */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        $similarity = 0;

        foreach ($this->providers as $provider) {
            $providerSimilarity = $provider->getSimilarityValue($baseTrack, $comparedTrack);
            $providerWeight = $this->getProviderWeight($provider);

            $similarity += ($providerSimilarity * $providerWeight);
        }

        return round(($similarity / $this->providersCount * 100) / $this->maxSimilarityValue);
    }

    /**
     * Przygotowuje moduł podobieństwa do użycia
     */
    private function setup(): void
    {
        $enabledProviders = array_filter(
            $this->availableProviders,
            fn($providerClass) => in_array($providerClass, $this->parameters['providers']['enabled'], true)
        );

        foreach ($enabledProviders as $providerClass) {
            $providerParameters = $this->parameters['providers']['parameters'][$providerClass] ?? null;

            /** @var ProviderInterface $provider */
            $provider = new $providerClass($providerParameters);

            if (empty($provider->getName())) {
                $message = sprintf('Provider class "%s" has invalid name (name can not be empty)', $providerClass);

                throw new \RuntimeException($message);
            }

            if (empty($provider->getSimilarityField())) {
                $message = sprintf('Provider "%s" has invalid similarity field', $provider->getName());

                throw new \RuntimeException($message);
            }

            $this->providers[] = $provider;

            unset($providerClass, $providerParameters, $provider);
        }

        $this->providersCount = count($this->providers);

        if ($this->providersCount === 0) {
            throw new \RuntimeException('At least one similarity provider should be enabled');
        }

        $this->providerWeights = $this->parameters['providers']['weights'];

        $maxSimilarityValue = array_reduce(
            $this->providers,
            function ($previousValue, ProviderInterface $provider) {
                $providerClass = get_class($provider);
                $previousValue += $provider->getMaxSimilarityValue() * $this->providerWeights[$providerClass];

                return $previousValue;
            },
            0
        );

        $this->maxSimilarityValue = $maxSimilarityValue / $this->providersCount;
    }

    /**
     * Zwraca kryteria, które muszą zostać spełnione, aby w trybie wyszukiwania
     * uznać utwór za podobny do podanego (i został pobrany z repozytorium)
     *
     * @param Track $baseTrack
     * @return array
     */
    private function getSimilarityCriteria(Track $baseTrack): array
    {
        $criteria = [
            'guid' => [ '$ne' => $baseTrack->getGuid() ]
        ];

        foreach ($this->providers as $provider) {
            // @todo: zastanowić się nad rezygnacją z getSimilarityField na rzecz zwracania całości w getCriteria
            $field = $provider->getSimilarityField();
            $criteria[$field] = $provider->getCriteria($baseTrack);
        }

        return $criteria;
    }

    /**
     * Sortuje listę podobnych utworów
     *
     * @param array $result
     * @return array
     */
    private function sort(array $result): array
    {
        $compare = static function (SimilarTrack $first, SimilarTrack $second) {
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

            $result = $first->getSecondTrack()->getYear() <=> $second->getSecondTrack()->getYear();

            if ($result !== 0) {
                return $result;
            }

            return 0;
        };

        usort($result, $compare);

        unset($compare);

        return $result;
    }

    private function getProviderWeight(ProviderInterface $provider): float
    {
        $providerClass = get_class($provider);
        $providerWeight = $this->providerWeights[$providerClass];

        return $providerWeight;
    }
}
