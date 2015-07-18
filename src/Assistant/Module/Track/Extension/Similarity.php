<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track;

/**
 * Moduł podobieństwa
 */
class Similarity
{
    /**
     * @var Similarity\Provider\CamelotKeyCode
     */
    protected $camelotKeyCode;

    /**
     * @var Similarity\Provider\Bpm
     */
    protected $bpm;

    /**
     * @var Similarity\Provider\Genre
     */
    protected $genre;

    /**
     * Lista dostawców podobieństwa
     *
     * @var array
     */
    private $providers = [
        'bpm',
        'camelotKeyCode',
        'genre',
        'year',
    ];

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
    private $maxSimilarity;

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
            $this->repository->findBy(
                $a = $this->getSimilarityCriteria($baseTrack)
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

        foreach ($this->providers as $provider) {
            $similarity += $this->{ $provider }->getSimilarity($baseTrack, $comparedTrack) * $this->weights[$provider];
        }

        return round(
            ($similarity / $this->providersCount * 100) / $this->maxSimilarity
        );
    }

    /**
     * Przygotowuje moduł podobieństwa do użycia
     */
    private function setup()
    {
        $this->weights = $this->parameters['weights'];

        $this->providersCount = count($this->providers);
        $this->maxSimilarity = 0;

        foreach ($this->providers as $provider) {
            $className = sprintf('%s\Similarity\Provider\%s', __NAMESPACE__, ucfirst($provider));

            $providerParameters = isset($this->parameters['provider'][$provider])
                ? $this->parameters['provider'][$provider]
                : null;

            $this->{ $provider } = new $className($providerParameters);

            $this->maxSimilarity += ($className::MAX_SIMILARITY_VALUE * $this->weights[$provider]);

            unset($className, $providerParameters, $provider);
        }

        $this->maxSimilarity /=  $this->providersCount;
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

        foreach ($this->providers as $provider) {
            $field = $this->{ $provider }->getMetadataField();
            $criteria[$field] = $this->{ $provider }->getCriteria($baseTrack);
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

        return $result;
    }
}
