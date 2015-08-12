<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Collection;
use Assistant\Module\Track;

/**
 * Writer dla elementów będących utworami muzycznymi
 */
class TrackWriter extends Collection\Extension\Writer implements WriterInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct(\MongoDB $db)
    {
        parent::__construct($db);

        $this->repository = new Track\Repository\TrackRepository($db);
    }

    /**
     * Zapisuje utwór muzyczny w bazie danych
     *
     * @param Track\Model\Track $track
     * @return Track\Model\Track
     */
    public function save($track)
    {
        $indexedTrack = $this->repository->findOneBy([ 'pathname' => $track->pathname ], [ 'metadata_md5' ]);

        if ($indexedTrack === null) {
            $this->assumeValidGuid($track);

            $this->repository->insert($track->toArray());
        } else {
            if ($track->metadata_md5 === $indexedTrack->metadata_md5) {
                throw new Exception\DuplicatedElementException(
                    sprintf('Track "%s" is already in database.', $track->guid)
                );
            }

            $this->repository->updateById($indexedTrack->_id, $track->toArray());
        }

        return $track;
    }

    /**
     * Zapewnia, że guid podanego utworu jest unikalny
     *
     * @param Track\Model\Track $track
     */
    private function assumeValidGuid(Track\Model\Track &$track)
    {
        $count = $this->repository->count([ 'guid' => new \MongoRegex(sprintf('/^%s(\d+)?/i', $track->guid)) ]);

        if ($count !== 0) {
            $track->guid .= sprintf('-%d', $count + 1);
        }
    }
}
