<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Collection\Model\CollectionItemInterface;
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
        private BackendClient $backendClient,
    ) {
    }

    /** Zapisuje utwór muzyczny w bazie danych */
    public function save(Track|CollectionItemInterface $collectionItem): Track
    {
        $indexedTrack = $this->trackService->getByPathname($collectionItem->getPathname());

        // może odtąd* powinno zostać przeniesione do serwisu lub repo?

        /** @noinspection PhpIfWithCommonPartsInspection, powyższy komentarz */
        if ($indexedTrack === null) {
            $collectionItem = $collectionItem->withGuid($this->getUniqueGuid($collectionItem));

            $result = $this->trackService->save($collectionItem);
        } else {
            $collectionItem = $collectionItem
                ->withId($indexedTrack->getId())
                ->withModifiedDate($indexedTrack->getModifiedDate());

            $result = $this->trackService->save($collectionItem);
        }

        // -- *dotąd

        if (!$indexedTrack && $result) {
            $this->backendClient->addToSimilarCollection($collectionItem);
        }

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
