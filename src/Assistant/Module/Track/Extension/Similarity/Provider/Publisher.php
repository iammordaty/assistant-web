<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

/**
 * Provider podobieństwa oparty na wytwórni.
 *
 * W muzyce klubowej wytwórnia jest mocnym wyznacznikiem estetyki: katalog jednej oficyny bywa
 * spójniejszy brzmieniowo niż cały gatunek. Sygnał jest dwustanowy, więc pełni rolę premii,
 * a nie miary odległości.
 */
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
