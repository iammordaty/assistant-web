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
        $name = $this->slugify->slugify($name);

        $searchCriteria = SearchCriteriaFacade::createFromName($name);
        $track = $this->trackRepository->getOneBy($searchCriteria);

        return $track;
    }

    public function findOneByGuid(string $guid): ?Track
    {
        $searchCriteria = SearchCriteriaFacade::createFromGuid($guid);
        $track = $this->trackRepository->getOneBy($searchCriteria);

        return $track;
    }

    public function findOneByPathname(string $pathname): ?Track
    {
        $searchCriteria = SearchCriteriaFacade::createFromPathname($pathname);
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
        $tracks = $this->trackRepository->getBy($searchCriteria, $sort, $limit, $skip);

        return $tracks;
    }

    public function save(Track $track): bool
    {
        $result = $this->trackRepository->save($track);

        return $result;
    }

    public function count(SearchCriteria $searchCriteria): int
    {
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
