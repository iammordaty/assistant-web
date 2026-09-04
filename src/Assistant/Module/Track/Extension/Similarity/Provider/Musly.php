<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksResultList;
use Assistant\Module\Track\Model\Track;

/**
 * Provider podobieństwa oparty na bibliotece Musly.
 *
 * Wartość pochodzi wprost z listy sąsiadów, gdzie wyliczana jest z odległości zwróconej przez musly.
 * Utwór spoza listy nie ma wyliczonej odległości, więc dostawca nie ma o nim zdania.
 *
 * Instancja pamięta listy sąsiadów dla wszystkich utworów bazowych, o które była pytana, ponieważ
 * pobranie listy to wywołanie zewnętrznego procesu. Dzięki temu siatka podobieństwa miksu, która
 * wraca do tych samych utworów w kolejnych wierszach, płaci za każdy utwór bazowy tylko raz.
 */
final class Musly extends AbstractProvider implements CandidateProviderInterface
{
    public const string NAME = 'Musly';

    /** @var array<string, SimilarTracksResultList> Listy sąsiadów wg ścieżki utworu bazowego */
    private array $similarTracks = [];

    /** @var array<string, true> Ścieżki, dla których pobranie listy zakończyło się błędem */
    private array $failedTracks = [];

    public function __construct(private SimilarTracksCollectionService $service)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): ?int
    {
        $similarTracks = $this->getSimilarTracksFor($baseTrack);

        if (!$similarTracks) {
            return null;
        }

        $similarityValue = $similarTracks->getSimilarityValue($comparedTrack->getFile());

        return $similarityValue !== null ? (int) round($similarityValue) : null;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): null
    {
        return null;
    }

    /** {@inheritDoc} */
    public function getCandidatePathnames(Track $baseTrack): array
    {
        $similarTracks = $this->getSimilarTracksFor($baseTrack);

        return $similarTracks !== null ? array_keys($similarTracks->getSimilarTracks()) : [];
    }

    /**
     * Zwraca listę sąsiadów utworu bazowego, pobierając ją najwyżej raz dla danej ścieżki.
     * Zwraca null, gdy lista jest niedostępna; nieudana próba również jest pamiętana, żeby jedna
     * awaria nie mnożyła wywołań zewnętrznego procesu.
     */
    private function getSimilarTracksFor(Track $baseTrack): ?SimilarTracksResultList
    {
        $pathname = $baseTrack->getPathname();

        if (isset($this->failedTracks[$pathname])) {
            return null;
        }

        if (isset($this->similarTracks[$pathname])) {
            return $this->similarTracks[$pathname];
        }

        try {
            $this->similarTracks[$pathname] = $this->service->getSimilarTracks($baseTrack->getFile());
        } catch (SimilarTracksCollectionException $e) {
            // @fixme: błąd powinien być komunikowany na froncie w normalny sposób
            d($e->getMessage());

            $this->failedTracks[$pathname] = true;

            return null;
        }

        return $this->similarTracks[$pathname];
    }
}
