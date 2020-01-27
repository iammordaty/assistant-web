<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB;
use MongoRegex;

/**
 * Writer dla elementów będących utworami muzycznymi
 */
class TrackWriter extends AbstractWriter
{
    /**
     * @var TrackRepository
     */
    private $repository;

    /**
     * @var BackendClient
     */
    private $backendClient;

    /**
     * {@inheritDoc}
     */
    public function __construct(MongoDB $db)
    {
        parent::__construct($db);

        $this->repository = new TrackRepository($db);
        $this->backendClient = new BackendClient();
    }

    /**
     * Zapisuje utwór muzyczny w bazie danych
     *
     * @param Track $track
     * @return Track
     */
    public function save($track)
    {
        /* @var $indexedTrack Track */
        $indexedTrack = $this->repository->findOneBy([ 'pathname' => $track->pathname ], [ 'metadata_md5' ]);

        if ($indexedTrack === null) {
            $this->assumeUniqueGuid($track);

            $result = $this->repository->insert($track);

            if ($result === true) {
                $this->backendClient->addToSimilarCollection($track);
            }
        } else {
            // TODO: zapis powinien odbyć się tylko wówczas, gdy zmieniły się dane,
            //       czyli md5 z metadanych są różne

            $track->_id = $indexedTrack->_id;
            $track->indexed_date = $indexedTrack->indexed_date;

            $this->repository->update($track);
        }

        unset($indexedTrack);

        return $track;
    }

    /**
     * Usuwa elementy znajdujące się w kolekcji
     *
     * @return int
     */
    public function clean()
    {
        return $this->repository->removeBy();
    }

    /**
     * Zapewnia, że guid podanego utworu jest unikalny
     *
     * @param Track $track
     */
    private function assumeUniqueGuid(Track &$track)
    {
        $count = $this->repository->count([ 'guid' => new MongoRegex(sprintf('/^%s(?:-\d+)?$/i', $track->guid)) ]);

        if ($count !== 0) {
            $track->guid .= sprintf('-%d', $count + 1);
        }
    }
}
