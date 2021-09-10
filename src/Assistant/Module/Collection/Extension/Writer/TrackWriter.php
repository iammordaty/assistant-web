<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Storage\Regex;
use Assistant\Module\Search\Extension\SearchCriteriaFacade as SearchCriteria;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;

/** Writer dla elementów będących utworami muzycznymi */
final class TrackWriter implements WriterInterface
{
    public function __construct(
        private TrackService $trackService,
        private TrackSearchService $searchService,
        private SimilarTracksCollectionService $similarTracksCollectionService,
    ) {
    }

    /** Zapisuje utwór muzyczny w bazie danych */
    public function save(Track|CollectionItemInterface $collectionItem): Track
    {
        $indexedTrack = $this->trackService->getByPathname($collectionItem->getPathname());

        // Utworu nie ma jeszcze bazie danych — zapisz go i dodaj do kolekcji podobnych utworów
        if ($indexedTrack === null) {
            $collectionItem = $collectionItem->withGuid($this->getUniqueGuid($collectionItem));

            $result = $this->trackService->save($collectionItem);

            if ($result) {
                $this->similarTracksCollectionService->add($collectionItem->getFile());
            }

            return $collectionItem;
        }

        // Utwór znajduje się w bazie danych, ale jego metadane zostały zmodyfikowane — zaktualizuj dane w bazie.
        $collectionItem = $collectionItem
            ->withId($indexedTrack->getId())
            ->withIndexedDate($indexedTrack->getIndexedDate())
            ->withModifiedDate($indexedTrack->getModifiedDate());

        $this->trackService->save($collectionItem);

        return $collectionItem;
    }

    /** Zwraca unikalny guid dla podanego utworu */
    private function getUniqueGuid(Track $track): string
    {
        $isGuidAvailable = ($this->trackService->getByGuid($track->getGuid()) === null);

        if ($isGuidAvailable) {
            return $track->getGuid();
        }

        $regex = Regex::create(sprintf('^%s(?:-\d+)?$', $track->getGuid()));
        $searchCriteria = SearchCriteria::createFromGuid($regex);

        $count = $this->searchService->count($searchCriteria);

        if ($count === 0) {
            return $track->getGuid();
        }

        $guid = sprintf('%s-%d', $track->getGuid(), $count + 1);

        return $guid;
    }
}
