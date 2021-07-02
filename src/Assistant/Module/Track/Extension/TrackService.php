<?php

// Wrzucone na szybko, być może powinno leżeć bliżej modelu
namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use SplFileInfo;

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

    public function save(Track $track): bool
    {
        $result = $this->trackRepository->save($track);

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
