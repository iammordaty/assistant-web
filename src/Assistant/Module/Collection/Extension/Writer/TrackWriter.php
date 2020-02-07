<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB\BSON\Regex;

/**
 * Writer dla elementów będących utworami muzycznymi
 */
class TrackWriter implements WriterInterface
{
    private TrackRepository $repository;

    private BackendClient $backendClient;

    /**
     * TrackWriter constructor.
     *
     * @param TrackRepository $repository
     * @param BackendClient $backendClient
     */
    public function __construct(TrackRepository $repository, BackendClient $backendClient)
    {
        $this->repository = $repository;
        $this->backendClient = $backendClient;
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
        $indexedTrack = $this->repository->findOneBy([ 'pathname' => $track->pathname ]);

        if ($indexedTrack === null) {
            $track->guid = $this->getUniqueGuid($track);

            $result = $this->repository->insert($track);

            if ($result->getInsertedCount() === 1) {
                $this->backendClient->addToSimilarCollection($track);
            }
        } else {
            $track->_id = $indexedTrack->_id;
            $track->modified_date = $indexedTrack->indexed_date;

            $this->repository->update($track);
        }

        unset($indexedTrack);

        return $track;
    }

    /**
     * Zwraca unikalny guid dla podanego utworu
     *
     * @param Track $track
     * @return string
     */
    private function getUniqueGuid(Track $track): string
    {
        $count = $this->repository->count([ 'guid' => new Regex(sprintf('^%s(?:-\d+)?$', $track->guid), 'i') ]);

        if ($count === 0) {
            return $track->guid;
        }

        $guid = $track->guid .= sprintf('-%d', $count + 1);

        return $guid;
    }
}
