<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

final class Publisher extends AbstractProvider
{
    /** {@inheritDoc} */
    public const string NAME = 'Publisher';

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): ?int
    {
        $basePublisher = $baseTrack->getPublisher();
        $comparedPublisher = $comparedTrack->getPublisher();

        if (!$basePublisher || !$comparedPublisher) {
            return null;
        }

        return $basePublisher === $comparedPublisher ? self::MAX_SIMILARITY_VALUE : 0;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): null
    {
        // wytwórnia nie może zawężać kandydatów, bo odcięłaby wszystkie utwory spoza jednej oficyny
        return null;
    }
}
