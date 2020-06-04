<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Extension\Backend\Exception\Exception as BackendException;
use Assistant\Module\Track\Model\Track;

class Musly extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public const NAME = 'Musly';

    /**
     * {@inheritDoc}
     */
    protected const SIMILARITY_FIELD = 'pathname';

    private BackendClient $backendClient;

    private ?array $similarTracks = null;

    public function __construct()
    {
        $this->backendClient = new BackendClient();
    }

    /**
     * {@inheritDoc}
     */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): int
    {
        if ($this->similarTracks === null) {
            try {
                $this->similarTracks = $this->backendClient->getSimilarTracks($baseTrack);
            } catch (BackendException $e) {
                // @todo: usunąć try-catch i łapać wyżej?
                unset($e);

                $this->similarTracks = [];
            }
        }

        return $this->similarTracks[$comparedTrack->pathname] ?? 0;
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
