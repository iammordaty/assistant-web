<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksResultList;
use Assistant\Module\Track\Model\Track;

/**
 * Provider podobieństwa oparty na bibliotece Musly.
 *
 * Instancja tej klasy cache'uje wyniki dla pierwszego baseTrack, dla którego zostanie wywołana
 * metoda getSimilarityValue(). Należy tworzyć nową instancję dla każdego nowego utworu bazowego.
 * Nie należy współdzielić instancji między różnymi wywołaniami getSimilarTracks().
 */
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
                d($e->getMessage());

                $this->similarTracks = SimilarTracksResultList::factory($baseTrack->getFile(), []);

                return 0;
            }
        }

        $similarityValue = (int) $this->similarTracks->getSimilarityValue($comparedTrack->getFile());

        return $similarityValue;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): null
    {
        return null;
    }
}
