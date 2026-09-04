<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksResultList;
use Assistant\Module\Track\Model\Track;

/**
 * Provider podobieństwa oparty na bibliotece Musly.
 *
 * Wartość wynika z pozycji utworu na liście sąsiadów, a nie ze zwróconej odległości. Odległości po
 * normalizacji Mutual Proximity są bardzo małe i ściśnięte, więc każdy sąsiad dostawałby niemal
 * identyczną wartość; informacja siedzi w kolejności listy.
 *
 * Instancja pamięta listy sąsiadów dla wszystkich utworów bazowych, o które była pytana, ponieważ
 * pobranie listy to wywołanie zewnętrznego procesu. Dzięki temu siatka podobieństwa miksu, która
 * wraca do tych samych utworów w kolejnych wierszach, płaci za każdy utwór bazowy tylko raz.
 */
final class Musly extends AbstractProvider implements CandidateProviderInterface
{
    /** {@inheritDoc} */
    public const string NAME = 'Musly';

    /** @var array<string, SimilarTracksResultList> */
    private array $similarTracks = [];

    /** @var array<string, array<string, int>> Pozycje sąsiadów, w kolejności rosnącej odległości */
    private array $neighbourRanks = [];

    /** @var array<string, true> Ścieżki, dla których pobranie listy zakończyło się błędem */
    private array $failedTracks = [];

    public function __construct(private SimilarTracksCollectionService $service)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): ?int
    {
        if ($this->getSimilarTracksFor($baseTrack) === null) {
            // lista sąsiadów jest niedostępna, więc dostawca nie ma zdania o tej parze
            return null;
        }

        $ranks = $this->neighbourRanks[$baseTrack->getPathname()];
        $rank = $ranks[$comparedTrack->getPathname()] ?? null;

        if ($rank === null) {
            return 0;
        }

        return (int) round(self::MAX_SIMILARITY_VALUE * (1 - $rank / count($ranks)));
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
            $similarTracks = $this->service->getSimilarTracks($baseTrack->getFile());
        } catch (SimilarTracksCollectionException $e) {
            // @fixme: błąd powinien być komunikowany na froncie w normalny sposób
            d($e->getMessage());

            $this->failedTracks[$pathname] = true;

            return null;
        }

        $this->similarTracks[$pathname] = $similarTracks;
        $this->neighbourRanks[$pathname] = array_flip(array_keys($similarTracks->getSimilarTracks()));

        return $similarTracks;
    }
}
