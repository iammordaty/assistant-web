<?php

// Wrzucone na szybko, być może powinno leżeć bliżej modelu
namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\Reader\FileReader;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;

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
        // @fixme: do czasu poprawienia w bazie
        // @fixme: do czasu poprawienia w kodzie js, który powinien zwracać całą ścieżkę
        $tempRootDit = '/collection';

        if (strpos($pathname, $tempRootDit) !== 0) {
            $pathname = $tempRootDit . $pathname;
        }

        if (!trim($pathname) || !is_readable($pathname)) {
            return null;
        }

        // sprawdź, czy ścieżka istnieje w bazie
        // jeśli ścieżka jest w bazie, załaduj metadane z bazy
        // jeśli ścieżki nie ma w bazie, załaduj metadane z pliku

        $absolutePathname = str_replace('/collection/', '', $pathname);

        return $this->fileReader->read(new SplFileInfo($pathname, $absolutePathname));
    }
}