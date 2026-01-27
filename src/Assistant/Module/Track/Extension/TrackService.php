<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use SplFileInfo;

final readonly class TrackService
{
    public function __construct(
        private FileReaderFacade $fileReader,
        private TrackLocationArbiter $arbiter,
        private TrackRepository $trackRepository,
    ) {
    }

    public function getByGuid(string $guid): ?Track
    {
        $criteria = SearchCriteriaFacade::createFromGuid($guid);

        return $this->trackRepository->getOneBy($criteria);
    }

    public function getByPathname(string $pathname): ?Track
    {
        $criteria = SearchCriteriaFacade::createFromPathname($pathname);

        return $this->trackRepository->getOneBy($criteria);
    }

    public function save(Track $track): bool
    {
        return $this->trackRepository->save($track);
    }

    public function remove(Track $track): bool
    {
        return $this->trackRepository->delete($track);
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
