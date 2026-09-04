<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksResult;
use Assistant\Module\Track\Model\Track;

/**
 * Provider podobieństwa oparty na bibliotece Musly.
 *
 * Wartość wynika z odległości zwróconej przez musly, a nie z pozycji utworu na liście sąsiadów.
 * Po normalizacji Mutual Proximity odległość wyraża wzajemny udział kolekcji znajdujący się bliżej
 * (d ≈ (r + r') / N), więc jest miarą porównywalną między utworami bazowymi i niezależną od liczby
 * pobranych sąsiadów. Pozycja na liście jest wobec niej funkcją stratną.
 *
 * Instancja pamięta listy sąsiadów dla wszystkich utworów bazowych, o które była pytana, ponieważ
 * pobranie listy to wywołanie zewnętrznego procesu. Dzięki temu siatka podobieństwa miksu, która
 * wraca do tych samych utworów w kolejnych wierszach, płaci za każdy utwór bazowy tylko raz.
 */
final class Musly extends AbstractProvider
{
    public const NAME = 'Musly';

    /** @var array<string, array<string, float>> Odległości sąsiadów wg ścieżki utworu bazowego */
    private array $neighbourDistances = [];

    /** @var array<string, true> Ścieżki, dla których pobranie listy zakończyło się błędem */
    private array $failedTracks = [];

    private ?int $collectionSize = null;

    public function __construct(private SimilarTracksCollectionService $service)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): ?int
    {
        $distances = $this->getNeighbourDistances($baseTrack);

        if ($distances === null) {
            // lista sąsiadów jest niedostępna, więc dostawca nie ma zdania o tej parze

            return null;
        }

        $distance = $distances[$comparedTrack->getPathname()] ?? null;

        if ($distance === null) {
            // utwór nie zmieścił się w czołówce listy, co nie znaczy, że jest niepodobny

            return null;
        }

        return $this->distanceToSimilarityValue($distance);
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): null
    {
        return null;
    }

    /**
     * Zwraca odległości sąsiadów utworu bazowego, pobierając listę najwyżej raz dla danej ścieżki.
     * Zwraca null, gdy lista jest niedostępna; nieudana próba również jest pamiętana, żeby jedna
     * awaria nie mnożyła wywołań zewnętrznego procesu.
     *
     * @return array<string, float>|null
     */
    private function getNeighbourDistances(Track $baseTrack): ?array
    {
        $pathname = $baseTrack->getPathname();

        if (isset($this->failedTracks[$pathname])) {
            return null;
        }

        if (isset($this->neighbourDistances[$pathname])) {
            return $this->neighbourDistances[$pathname];
        }

        try {
            $similarTracks = $this->service->getSimilarTracks($baseTrack->getFile());
        } catch (SimilarTracksCollectionException $e) {
            // @fixme: błąd powinien być komunikowany na froncie w normalny sposób
            d($e->getMessage());

            $this->failedTracks[$pathname] = true;

            return null;
        }

        $this->neighbourDistances[$pathname] = array_map(
            fn (SimilarTracksResult $similarTrack): float => $similarTrack->getDistance(),
            $similarTracks->getSimilarTracks()
        );

        return $this->neighbourDistances[$pathname];
    }

    /**
     * Przelicza odległość Mutual Proximity na wartość w skali 0-MAX_SIMILARITY_VALUE.
     *
     * Najlepsza osiągalna odległość to 2/N, mediana to 1, dlatego skala jest logarytmiczna:
     * rozciąga czołówkę listy, gdzie odległości różnią się o rzędy wielkości, i — dzięki odniesieniu
     * do rozmiaru kolekcji — pozostaje porównywalna między bibliotekami różnej wielkości.
     * Wartość maksymalna oznacza parę wzajemnie najbliższych sąsiadów, zero — odległość mediany.
     */
    private function distanceToSimilarityValue(float $distance): ?int
    {
        $collectionSize = $this->getCollectionSize();

        if ($collectionSize === null) {
            // bez rozmiaru kolekcji skala nie ma odniesienia

            return null;
        }

        if ($collectionSize < 4) {
            return self::MAX_SIMILARITY_VALUE;
        }

        // 0 jest zwracane dla utworu porównanego z samym sobą
        $bestPossible = 2.0 / $collectionSize;
        $distance = min(max($distance, $bestPossible), 1.0);

        $normalized = log10(1.0 / $distance) / log10($collectionSize / 2.0);

        return (int) round(self::MAX_SIMILARITY_VALUE * max(0.0, min(1.0, $normalized)));
    }

    /** Zwraca liczbę utworów w kolekcji musly albo null, gdy kolekcja jest niedostępna */
    private function getCollectionSize(): ?int
    {
        if ($this->collectionSize === null) {
            try {
                $this->collectionSize = count($this->service->getTracks());
            } catch (SimilarTracksCollectionException) {
                // zero oznacza nieudaną próbę i zapobiega ponownym wywołaniom zewnętrznego procesu
                $this->collectionSize = 0;
            }
        }

        return $this->collectionSize ?: null;
    }
}
