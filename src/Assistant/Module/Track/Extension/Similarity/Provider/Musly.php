<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksResultList;
use Assistant\Module\Track\Model\Track;

final class Musly extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public const NAME = 'Musly';

    /**
     * {@inheritDoc}
     */
    protected const SIMILARITY_FIELD = 'pathname';

    private SimilarTracksCollectionService $service;

    private ?SimilarTracksResultList $similarTracks = null;

    public function __construct()
    {
        // @fixme: to jest zło
        $values = [ 'collection' => [ 'metadata_dirs' => [ 'music_similarity' => '/metadata/musly' ] ] ];
        $this->service = new SimilarTracksCollectionService(new Config($values));
    }

    /**
     * {@inheritDoc}
     */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        if ($this->similarTracks === null) {
            try {
                $this->similarTracks = $this->service->getSimilarTracks($baseTrack->getFile());
            } catch (SimilarTracksCollectionException $e) {
                // @todo: usunąć try-catch i łapać wyżej?
                // @fixme: błąd powinien być komunikowany na froncie, a nie wyciszany

                unset($e);
            }
        }

        $similarityValue = $this->similarTracks?->getSimilarityValue($comparedTrack->getFile()) ?? 0;

        return $similarityValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track $baseTrack): array
    {
        return [
            '$exists' => true
        ];
    }
}
