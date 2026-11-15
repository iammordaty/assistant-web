<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierTag;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use SplFileInfo;

class TrackService
{
    public function __construct(
        private readonly FileReaderFacade $fileReader,
        private readonly TrackLocationArbiter $arbiter,
        private readonly TrackRepository $trackRepository,
        private readonly MusicClassifierService $musicClassifierService,
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

    /**
     * AI: jedyne miejsce, z którego strony utworu biorą tagi. Dziś wynik pochodzi z cache Music Classifier;
     * docelowo tagi utworów z kolekcji będą czytane z Track::getTags() i zmieni się tylko ta metoda.
     *
     * @return MusicClassifierTag[]|null null, gdy klasyfikator nie zwrócił wyniku
     */
    public function getTags(Track|IncomingTrack $track): ?array
    {
        try {
            $result = $this->musicClassifierService->analyze($track->getFile());
        } catch (MusicClassifierException) {
            return null;
        }

        return $result->getTags();
    }
}
