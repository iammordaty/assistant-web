<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

/** AI: ulubione działają jak filtr: zawężają kandydatów, ale nie wpływają na wynik podobieństwa */
final class Favorite extends AbstractProvider
{
    /** {@inheritDoc} */
    public const string NAME = 'Favorite';

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): null
    {
        return null;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): true
    {
        return true;
    }
}
