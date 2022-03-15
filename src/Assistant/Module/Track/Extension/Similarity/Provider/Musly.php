<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksResultList;
use Assistant\Module\Track\Model\Track;

final class Musly extends AbstractProvider
{
    public const NAME = 'Musly';

    private ?SimilarTracksResultList $similarTracks = null;

    public function __construct(private SimilarTracksCollectionService $service)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        if ($this->similarTracks === null) {
            try {
                $this->similarTracks = $this->service->getSimilarTracks($baseTrack->getFile());
            } catch (SimilarTracksCollectionException $e) {
                // @idea: usunąć try-catch i łapać wyżej?

                // @fixme: błąd powinien być komunikowany na froncie w normalny sposób
                var_dump($e->getMessage());

                return 0;
            }
        }

        $similarityValue = (int) $this->similarTracks->getSimilarityValue($comparedTrack->getFile());

        return $similarityValue;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): mixed
    {
        return null;
    }
}
