<?php

// Wrzucone na szybko, być może powinno leżeć bliżej modelu
namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReader;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Cocur\Slugify\SlugifyInterface;
use DateTime;
use MongoDB\BSON\Regex;
use SplFileInfo;

final class TrackService
{
    private FileReader $fileReader;

    private TrackRepository $trackRepository;

    private SlugifyInterface $slugify;

    public function __construct(FileReader $fileReader, TrackRepository $repository, SlugifyInterface $slugify)
    {
        $this->fileReader = $fileReader;
        $this->trackRepository = $repository;
        $this->slugify = $slugify;
    }

    public function getTrackByName(string $name): ?Track
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            return null;
        }

        $guid = new Regex($this->slugify->slugify($trimmedName), 'i');
        $track = $this->trackRepository->getByGuid($guid);

        if (!$track) {
            $query = new Regex($trimmedName, 'i');
            $track = $this->trackRepository->getByName($query);
        }

        return $track;
    }

    public function createFromFile(string $pathname): ?Track
    {
        if (!trim($pathname) || !is_readable($pathname)) {
            return null;
        }

        return $this->fileReader->read(new SplFileInfo($pathname));
    }
}
