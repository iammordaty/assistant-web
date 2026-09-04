<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksResultList;
use Assistant\Module\Track\Model\Track;

/** Provider podobieństwa oparty na bibliotece Musly. */
final class Musly extends AbstractProvider
{
    public const string NAME = 'Musly';

    /** @var array<string, SimilarTracksResultList> */
    private array $guidToSimilarTracksMap = [];

    public function __construct(private SimilarTracksCollectionService $service)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        $similarTracks = $this->getSimilarTracksFor($baseTrack);

        $similarityValue = (int) $similarTracks->getSimilarityValue($comparedTrack->getFile());

        return $similarityValue;
    }

    /** Zwraca listę sąsiadów utworu bazowego, pobierając ją najwyżej raz dla danej ścieżki */
    private function getSimilarTracksFor(Track $baseTrack): SimilarTracksResultList
    {
        $guid = $baseTrack->getGuid();

        if (isset($this->guidToSimilarTracksMap[$guid])) {
            return $this->guidToSimilarTracksMap[$guid];
        }

        try {
            $similarTracks = $this->service->getSimilarTracks($baseTrack->getFile());
        } catch (SimilarTracksCollectionException $e) {
            // @idea: usunąć try-catch i łapać wyżej?

            // @fixme: błąd powinien być komunikowany na froncie w normalny sposób
            d($e->getMessage());

            $similarTracks = SimilarTracksResultList::factory($baseTrack->getFile(), []);
        }

        $this->guidToSimilarTracksMap[$guid] = $similarTracks;

        return $similarTracks;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): null
    {
        return null;
    }
}
