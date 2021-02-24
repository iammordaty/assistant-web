<?php

// Wrzucone na szybko, być może powinno leżeć bliżej modelu
namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReader;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use SplFileInfo;

final class TrackBuilder
{
    private FileReader $fileReader;

    private TrackRepository $repository;

    public function __construct(FileReader $fileReader, TrackRepository $repository)
    {
        $this->fileReader = $fileReader;
        $this->repository = $repository;
    }

    public function fromFile(string $pathname): ?Track
    {
        if (!trim($pathname) || !is_readable($pathname)) {
            return null;
        }

        return $this->fileReader->read(new SplFileInfo($pathname));
    }
}