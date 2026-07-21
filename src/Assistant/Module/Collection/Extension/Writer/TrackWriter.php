<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Search\Extension\Criteria\Regex;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade as SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;

/** Writer dla elementów będących utworami muzycznymi */
final readonly class TrackWriter implements WriterInterface
{
    public function __construct(
        private MusicClassifierService $musicClassifierService,
        private TrackService $trackService,
        private TrackSearchService $searchService,
        private SimilarTracksCollectionService $similarTracksCollectionService,
    ) {
    }

    /** Zapisuje utwór muzyczny w bazie danych */
    public function save(Track|CollectionItemInterface $collectionItem): Track
    {
        $indexedTrack = $this->trackService->getByPathname($collectionItem->getPathname());
        $isNewTrack = !$indexedTrack;

        if ($isNewTrack) {
            $guid = $this->getUniqueGuid($collectionItem);
            $collectionItem = $collectionItem->withGuid($guid);

            $this->addToSimilarTracksCollection($collectionItem);

            $this->musicClassifierService->analyze($collectionItem->getFile());
        } else {
            $collectionItem = $collectionItem
                ->withId($indexedTrack->getId())
                ->withIsFavorite($indexedTrack->getIsFavorite())
                ->withTags($indexedTrack->getTags())
                ->withIndexedDate($indexedTrack->getIndexedDate())
                ->withModifiedDate($indexedTrack->getModifiedDate());

            // Jeśli wyniku zmiany metadanych zmiana uległa nazwa pliku, to trzeba do dodać ponownie
            if (!$this->isInSimilarTracksCollection($indexedTrack)) {
                $this->addToSimilarTracksCollection($indexedTrack);
            }
        }

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

    private function isInSimilarTracksCollection(Track $collectionItem): bool
    {
        return $this->similarTracksCollectionService->hasTrack($collectionItem->getFile());
    }

    private function addToSimilarTracksCollection(Track $collectionItem): void
    {
        $this->similarTracksCollectionService->add($collectionItem->getFile());
    }
}
