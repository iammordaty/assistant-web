<?php

// Wrzucone na szybko, być może powinno leżeć bliżej modelu
namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\File\Model\IncomingTrack;
use Assistant\Module\Search\Extension\SearchCriteria;
use Assistant\Module\Search\Extension\SearchCriteriaFacade;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use SplFileInfo;
use Traversable;

final class TrackService
{
    public function __construct(
        private FileReaderFacade $fileReader,
        private SlugifyService $slugify,
        private TrackRepository $trackRepository,
        private TrackLocationArbiter $arbiter,
    ) {
    }

    public function findOneByName(string $name): ?Track
    {
        $guid = $this->slugify->slugify($name);

        if ($guid === '') {
            return null;
        }

        $searchCriteria = SearchCriteriaFacade::createFromName($name);
        $track = $this->trackRepository->getOneBy($searchCriteria);

        return $track;
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return Track[]|Traversable
     */
    public function findBy(
        SearchCriteria $searchCriteria,
        ?array $sort = null,
        ?int $limit = null,
        ?int $skip = null
    ): array|Traversable {
        // rozwiązanie na szybko. najlepiej gdyby SearchCriteria samo slugify'owało nazwę, ale obecnie nie ma
        // dostępu do klasy slugify; a może nie powinno. Do zastanowienia się.
        // update 27.05: może do SearchCriteriaFacade::createFromName?

        if ($searchCriteria->getName()) {
            $name = $this->slugify->slugify($searchCriteria->getName());

            $searchCriteria = $searchCriteria->withName($name);
        }

        $tracks = $this->trackRepository->getBy($searchCriteria, $sort, $limit, $skip);

        return $tracks;
    }

    public function count(SearchCriteria $searchCriteria): int
    {
        // rozwiązanie na szybko. najlepiej gdyby SearchCriteria samo slugify'owało nazwę, ale obecnie nie ma
        // dostępu do klasy slugify; a może nie powinno. Do zastanowienia się.

        if ($searchCriteria->getName()) {
            $name = $this->slugify->slugify($searchCriteria->getName());

            $searchCriteria = $searchCriteria->withName($name);
        }

        $tracks = $this->trackRepository->countBy($searchCriteria);

        return $tracks;
    }

    public function getLocationArbiter(): TrackLocationArbiter
    {
        return $this->arbiter;
    }

    public function createFromFile(string $pathname): IncomingTrack|Track|null
    {
        if (!trim($pathname) || !is_readable($pathname)) {
            return null;
        }

        return $this->fileReader->read(new SplFileInfo($pathname));
    }
}
