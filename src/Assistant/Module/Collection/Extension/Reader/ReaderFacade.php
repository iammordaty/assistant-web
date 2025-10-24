<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use SplFileInfo;

/** Fasada dla procesorów przetwarzających elementy znajdujące się w kolekcji */
final class ReaderFacade
{
    public function __construct(
        private DirectoryReader $directoryReader,
        private FileReaderFacade $fileReader,
    ) {
    }

    public function read(SplFileInfo $node): IncomingTrack|Directory|Track
    {
        if ($node->isDir()) {
            return $this->directoryReader->read($node);
        }

        assert($node->isFile());

        return $this->fileReader->read($node);
    }
}
