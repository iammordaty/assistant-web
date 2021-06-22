<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Model\CollectionItemInterface;
use Assistant\Module\Common\Storage\Regex;
use Assistant\Module\Search\Extension\SearchCriteria;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;

/** Writer dla elementów będących utworami muzycznymi */
final class TrackWriter implements WriterInterface
{
    public function __construct(
        private TrackService $trackService,
        private BackendClient $backendClient,
    ) {
    }

    /** Zapisuje utwór muzyczny w bazie danych */
    public function save(Track|CollectionItemInterface $collectionItem): Track
    {
        $indexedTrack = $this->trackService->findOneByPathname($collectionItem->getPathname());

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
        //<editor-fold desc="Wyjaśnienie dodatkowego warunku na guid">
        /*
            pierwszy warunek, pomimo tego że wydaje się że może być pokryty przez drugi, zabezpiecza guid
            przed sytuacją w której przetwarzane są dwa pliki, z których jeden ma kolejny nr remiksu w nazwie,
            a jest przetwarzany jako pierwszy. w takiej sytuacji nie ma miejsca dla "oryginalnego" guid-a, np.:
            /collection/Singles/2009/08. sierpień/R.I.O/Shine On/R.I.O - 01 - Shine On [Original Mix].mp3
            /collection/Singles/2009/08. sierpień/R.I.O/Shine On/R.I.O - 03 - Shine On [Radio Mix 2].mp3 [1]
            /collection/Singles/2009/08. sierpień/R.I.O/Shine On/R.I.O - 10 - Shine On [Spencer & Hill Remix].mp3
            /collection/Singles/2009/08. sierpień/R.I.O/Shine On/R.I.O - 02 - Shine On [Radio Mix].mp3 [2]
            dla pliku [2] generowany jest guid z nr porządkowym ("r-i-o-shine-on-radio-mix-2"),
            który jest już zajęty - w sposób prawidłowy - przez plik [1]
        */
        //</editor-fold>

        $regex = Regex::exact($track->getGuid());
        $searchCriteria = new SearchCriteria(guid: $regex);

        $count = $this->trackService->count($searchCriteria);

        if ($count === 0) {
            return $track->getGuid();
        }

        $regex = Regex::create(sprintf('^%s(?:-\d+)?$', $track->getGuid()));
        $searchCriteria = new SearchCriteria(guid: $regex);

        $count = $this->trackService->count($searchCriteria);

        if ($count === 0) {
            return $track->getGuid();
        }

        $guid = sprintf('%s-%d', $track->getGuid(), $count + 1);

        return $guid;
    }
}
