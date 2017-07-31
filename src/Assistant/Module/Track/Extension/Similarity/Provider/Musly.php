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
                $this->similarTracks = (new Common\Extension\Backend\Client())->getSimilarTracks(
                    $baseTrack,
                    $this->getSimilarYears($baseTrack)
                );
            } catch (Common\Extension\Backend\Exception\Exception $e) {
                unset($e);

                $this->similarTracks = [];
            }
        }

        $similarity = 0;

        foreach ($this->similarTracks as $similarTrack) {
            if ($comparedTrack->pathname === $similarTrack['pathname']) {
                $similarity = $similarTrack['similarity'];

                break;
            }
        }

        return $similarity;
    }

    /**
     * @param Track\Model\Track $track
     * @return array
     */
    public function getSimilarYears(Track\Model\Track $track)
    {
        $years = [
            $track->year - 1,
            $track->year,
        ];

        if ($track->year < ($currentYear = (new \DateTime())->format('Y'))) {
            $years[] = $track->year + 1;
        }

        return $years;
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
