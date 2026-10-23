<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
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
     * AI: brak lub uszkodzony wynik klasyfikatora ukrywa cechy Essentii, zamiast blokować stronę utworu
     *
     * ponytail: plik wyniku (kilkaset kB JSON) jest parsowany przy każdym wyświetleniu strony; gdy zacznie to
     * ważyć, cechy warto zapisywać w bazie podczas indeksowania
     */
    public function getCharacter(Track|IncomingTrack $track): ?TrackCharacter
    {
        try {
            $result = $this->musicClassifierService->getResult($track->getFile());
        } catch (MusicClassifierException) {
            return null;
        }

        return $result ? TrackCharacter::fromMusicClassifierResult($result) : null;
    }
}
