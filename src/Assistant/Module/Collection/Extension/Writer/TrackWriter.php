<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
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
        private readonly Config $config,
        private readonly MusicClassifierService $musicClassifierService,
        private readonly TrackService $trackService,
        private readonly TrackSearchService $searchService,
        private readonly SimilarTracksCollectionService $similarTracksCollectionService,
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

            $classificationResult = $this->musicClassifierService->analyze($collectionItem->getFile());
            $this->moveClassificationResultFile($collectionItem, $classificationResult);
        } else {
            $collectionItem = $collectionItem
                ->withId($indexedTrack->getId())
                ->withIndexedDate($indexedTrack->getIndexedDate())
                ->withModifiedDate($indexedTrack->getModifiedDate());
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

    private function addToSimilarTracksCollection(Track $collectionItem): void
    {
        $this->similarTracksCollectionService->add($collectionItem->getFile());
    }

    /**
     * Przenosi roboczy plik z wynikiem klasyfikacji utworu do katalogu z metadanymi, wg poniższego schematu
     * /collection/a/b/c/track.mp3 -> /metadata/essentia/a/b/c/track.json
     **/
    private function moveClassificationResultFile(
        Track $track,
        MusicClassifierResult $classificationResult,
    ): void {
        $collectionRootDir = $this->config->get('collection.root_dir');
        $classifierMetadataDir = $this->config->get('collection.metadata_dirs.music_classifier');

        $newClassificationResultPathname = str_replace(
            [ $collectionRootDir, $track->getFile()->getExtension() ],
            [ $classifierMetadataDir, $classificationResult->getFile()->getExtension() ],
            $track->getPathname()
        );

        $parent = dirname($newClassificationResultPathname);

        if (!file_exists($parent)) {
            mkdir($parent, recursive: true);
        }

        rename(
            from: $classificationResult->getFile()->getPathname(),
            to: $newClassificationResultPathname,
        );
    }
}
