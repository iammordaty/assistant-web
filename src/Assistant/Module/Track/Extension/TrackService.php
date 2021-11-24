<?php

// Wrzucone na szybko, być może powinno leżeć bliżej modelu
namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Search\Extension\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use DateTime;
use SplFileInfo;
use Traversable;

// @idea: Być może należałoby rozdzielić klasę TrackService zajmującą się pojedynczymi utworami od
//        klasy zajmującej się listą (vide getByDirectory, getRecent). Jednocześnie wypadałoby
//        rozdzielić kod SearchService-a, który wcześniej był częścią kontrolera od metod ogólnych,
//        przy czym może to rozwiąże się samo przy pierwszym kroku.
final class TrackService
{
    public function __construct(
        private FileReaderFacade $fileReader,
        private TrackLocationArbiter $arbiter,
        private TrackRepository $trackRepository,
        private TrackSearchService $searchService,
    ) {
    }

    public function getByGuid(string $guid): ?Track
    {
        return $this->searchService->findOneByGuid($guid);
    }

    public function getByPathname(string $pathname): ?Track
    {
        return $this->searchService->findOneByPathname($pathname);
    }

    /**
     * @param Directory $directory
     * @return Track[]|Traversable
     */
    public function getByDirectory(Directory $directory): array|Traversable
    {
        $searchCriteria = SearchCriteriaFacade::createFromParent($directory->getGuid());

        $tracks = $this->searchService->findBy(
            $searchCriteria,
            [ 'guid' => Storage::SORT_ASC ]
        );

        return $tracks;
    }

    /**
     * @param DateTime|null $minIndexedDate
     * @param int|null $limit
     * @return Track[]|Traversable
     */
    public function getRecent(?DateTime $minIndexedDate = null, ?int $limit = null): array|Traversable
    {
        if (!$minIndexedDate) {
            $minIndexedDate = new DateTime();

            $minIndexedDate->modify('-3 years first day of january');
        }

        $searchCriteria = SearchCriteriaFacade::createFromMinIndexedDate($minIndexedDate);

        $tracks = $this->searchService->findBy(
            $searchCriteria,
            [ 'indexed_date' => Storage::SORT_DESC ],
            $limit,
        );

        return $tracks;
    }

    public function save(Track $track): bool
    {
        $result = $this->trackRepository->save($track);

        return $result;
    }

    public function remove(Track $track): bool
    {
        $result = $this->trackRepository->delete($track);

        return $result;
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
