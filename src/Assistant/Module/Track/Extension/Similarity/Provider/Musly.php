<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\Provider as BaseProvider;
use Assistant\Module\Track;
use Assistant\Module\Common;

class Musly extends BaseProvider
{
    /**
     * {@inheritDoc}
     */
    const METADATA_FIELD = 'pathname';

    /**
     * @var array|null
     */
    private $similarTracks = null;

    /**
     * {@inheritDoc}
     */
    public function getSimilarity(Track\Model\Track $baseTrack, Track\Model\Track $comparedTrack)
    {
        if ($this->similarTracks === null) {
            try {
                $this->similarTracks = (new Common\Extension\Backend\Client())->getSimilarTracks($baseTrack);
            } catch (Common\Extension\Backend\Exception\Exception $e) {
                unset($e);

                $this->similarTracks = [];
            }
        }

        return isset($this->similarTracks[$comparedTrack->pathname])
            ? $this->similarTracks[$comparedTrack->pathname]
            : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getCriteria(Track\Model\Track $baseTrack)
    {
        unset($baseTrack);

        return [
            '$exists' => true
        ];
    }
}
