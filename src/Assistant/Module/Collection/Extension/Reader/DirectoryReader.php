<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Directory\Model\Directory;
use SplFileInfo;

/**
 * Klasa, której zadaniem jest odczytywanie katalogów znajdujących się w kolekcji
 */
final class DirectoryReader implements ReaderInterface
{
    public function read(SplFileInfo $node): Directory
    {
        $modifiedTimestamp = (new \DateTime())->setTimestamp($node->getMTime());
        $indexedTimestamp = new \DateTime();

        $directory = new Directory(
            null,
            $node->getBasename(),
            $node->getBasename(),
            $node->getPath(),
            $node->getPathname(),
            $modifiedTimestamp,
            $indexedTimestamp,
        );

        return $directory;
    }
}
