<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track;

/**
 * Moduł podobieństwa
 */
class Similarity
{
    /**
     * Lista nazw używanych dostawców podobieństwa
     *
     * @var array
     */
    private $providerNames = [ ];

    /**
     * Lista dostawców podobieństwa
     *
     * @see setup()
     * @see $providerNames
     *
     * @var Similarity\Provider[]
     */
    private $providers = [ ];

    /**
     * Liczba dostawców
     *
     * @see $providers
     * @var integer
     */
    private $providersCount;

    /**
     * @var Track\Repository\TrackRepository
     */
    private $repository;

    /**
     * Parametry modułu
     *
     * @var array
     */
    private $parameters;

    /**
     * Wagi dostawców podobieństwa
     *
     * @var array
     */
    private $weights;

    /**
     * Maksymalna, uwzględniająca wagi dostawców, wartość podobieństwa, która może zostać zwrócona
     *
     * @var integer
     */
    private $maxSimilarityValue;

    /**
     * Konstruktor
     *
     * @param Track\Repository\TrackRepository $repository
     * @param array $parameters
     */
    public function __construct(Track\Repository\TrackRepository $repository, array $parameters)
    {
        $this->repository = $repository;
        $this->parameters = $parameters;

        $this->setup();
    }

    /**
     * Zwraca utwory podobne do podanego
     *
     * @param Track\Model\Track $baseTrack
     * @return array
     */
    public function getSimilarTracks(Track\Model\Track $baseTrack)
    {
        $similarTracks = array_map(
            function ($similarTrack) use ($baseTrack) {
                return [
                    'track' => $similarTrack,
                    'value' => $this->getSimilarityValue($baseTrack, $similarTrack),
                ];
            },
            iterator_to_array(
                $this->repository->findBy(
                    $this->getSimilarityCriteria($baseTrack)
                )
            )
        );

        // odrzuć wartości poniżej progu i/lub odrzuć nadmiarowe
        $result = array_slice(
            array_filter(
                $similarTracks,
                function ($similar) {
                    return $similar['value'] > $this->parameters['limit']['value'];
                }
            ),
            0,
            $this->parameters['limit']['tracks']
        );

        unset($similarTracks);

        return $this->sort($result);
    }

    /**
     * Oblicza podobieństwo pomiędzy utworami
     *
     * @param Track\Model\Track $baseTrack
     * @param Track\Model\Track $comparedTrack
     * @return int
     */
    public function getSimilarityValue(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack)
    {
        $similarity = 0;

        foreach ($this->providerNames as $providerName) {
            $similarity += $this->providers[$providerName]
                ->getSimilarity($baseTrack, $comparedTrack) * $this->weights[$providerName];
        }

        return round(
            ($similarity / $this->providersCount * 100) / $this->maxSimilarityValue
        );
    }

    /**
     * Przygotowuje moduł podobieństwa do użycia
     */
    private function setup()
    {
        $this->providerNames = $this->parameters['providers']['names'];
        $this->providersCount = count($this->providerNames);

        $this->weights = [];
        $this->maxSimilarityValue = 0;

        foreach ($this->providerNames as $providerName) {
            $providerClassName = sprintf('%s\Similarity\Provider\%s', __NAMESPACE__, ucfirst($providerName));

            $providerParameters = isset($this->parameters['providers']['parameters'][$providerName])
                ? $this->parameters['providers']['parameters'][$providerName]
                : null;

            $this->providers[$providerName] = new $providerClassName($providerParameters);
            $this->weights[$providerName] = $providerParameters['weight'];

            $this->maxSimilarityValue += ($providerClassName::MAX_SIMILARITY_VALUE * $this->weights[$providerName]);

            unset($providerName, $providerClassName, $providerParameters, $providerName);
        }

        $this->maxSimilarityValue /= $this->providersCount;

        var_dump($this->providerNames);
    }

    /**
     * Zwraca kryteria, które muszą zostać spełnione, aby w trybie wyszukiwania
     * uznać utwór za podobny do podanego (i został pobrany z repozytorium)
     *
     * @param Track\Model\Track $baseTrack
     * @return array
     */
    private function getSimilarityCriteria(Track\Model\Track $baseTrack)
    {
        $criteria = [
            'guid' => [ '$ne' => $baseTrack->guid ]
        ];

        foreach ($this->providerNames as $providerName) {
            $field = $this->providers[$providerName]->getMetadataField();
            $criteria[$field] = $this->providers[$providerName]->getCriteria($baseTrack);
        }

        return $criteria;
    }

    /**
     * Sortuje listę podobnych utworów
     *
     * @param array $result
     * @return array
     */
    private function sort(array $result)
    {
        $compare = function ($a, $b) {
            return $a === $b ? 0 : ($a > $b ? -1 : 1);
        };

        usort(
            $result,
            function ($first, $second) use ($compare) {
                // podobieństwo malejąco
                if (($result = $compare($first['value'], $second['value'])) !== 0) {
                    return $result;
                }

                // rok malejąco
                if (($result = $compare($first['track']->year, $second['track']->year)) !== 0) {
                    return $result;
                }

                // guid rosnąco
                if (($result = $compare($first['track']->guid, $second['track']->guid)) !== 0) {
                    return $result * -1;
                }

                return 0;
            }
        );

        unset($compare);

        return $result;
    }
}
