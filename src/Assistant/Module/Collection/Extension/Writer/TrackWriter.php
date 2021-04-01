<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Model\CollectionItemInterface;
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
     * @param Track|CollectionItemInterface $collectionItem
     * @return Track
     */
    public function save(CollectionItemInterface $collectionItem): Track
    {
        /* @var $indexedTrack Track */
        $indexedTrack = $this->repository->getByPathname($collectionItem);

        // może odtąd* powinno zostać przeniesione do serwisu lub repo?

        if ($indexedTrack === null) {
            $collectionItem = $collectionItem->withGuid($this->getUniqueGuid($collectionItem));

            $result = $this->repository->save($collectionItem);
        } else {
            $collectionItem = $collectionItem
                ->withId($indexedTrack->getId())
                ->withModifiedDate($indexedTrack->getModifiedDate());

            $result = $this->repository->save($collectionItem);
        }

        // -- *dotąd

        if (!$indexedTrack && $result) {
            $this->backendClient->addToSimilarCollection($collectionItem);
        }

        return $collectionItem;
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
