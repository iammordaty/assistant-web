<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\File\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use SplFileInfo;

/**
 * Fasada dla procesorów przetwarzających elementy znajdujące się w kolekcji
*/
final class ReaderFacade
{
    public function __construct(
        private DirectoryReader $directoryReader,
        private FileReaderFacade $fileReader,
    ) {
    }

    /**
     * @param SplFileInfo $node
     * @return Directory|Track|IncomingTrack
     */
    public function read(SplFileInfo $node)
    {
        if ($node->isDir()) {
            return $this->directoryReader->read($node);
        }

        assert($node->isFile());

        return $this->fileReader->read($node);
    }
}
