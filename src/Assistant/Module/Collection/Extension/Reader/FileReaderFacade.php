<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Track\Extension\TrackLocationArbiter;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use SplFileInfo;

/**
 * Fasada dla klas których zadaniem jest odczytywanie plików (utworów muzycznych)
 */
final class FileReaderFacade implements ReaderInterface
{
    public function __construct(
        private FileReader $fileReader,
        private TrackLocationArbiter $arbiter,
        private IncomingFileReader $incomingFileReader,
    ) {
    }

    public function read(SplFileInfo $node): IncomingTrack|Track
    {
        $fileReader = $this->arbiter->isInCollection($node)
            ? $this->fileReader
            : $this->incomingFileReader;

        return $fileReader->read($node);
    }
}
