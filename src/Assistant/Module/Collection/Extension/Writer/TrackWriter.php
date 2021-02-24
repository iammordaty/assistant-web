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
        $indexedTrack = $this->repository->findOneBy([ 'pathname' => $track->getPathname() ]);

        if ($indexedTrack === null) {
            $track->setGuid($this->getUniqueGuid($track));

            $result = $this->repository->insert($track);

            if ($result->getInsertedCount() === 1) {
                $this->backendClient->addToSimilarCollection($track);
            }
        } else {
            $track->setId($indexedTrack->getId());
            $track->setModifiedDate($indexedTrack->getModifiedDate());

            $this->repository->update($track);
        }

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
        $count = $this->repository->count([ 'guid' => new Regex(sprintf('^%s(?:-\d+)?$', $track->getGuid()), 'i') ]);

        if ($count === 0) {
            return $track->getGuid();
        }

        $guid = sprintf('%s-%d', $track->getGuid(), $count + 1);

        return $guid;
    }
}
