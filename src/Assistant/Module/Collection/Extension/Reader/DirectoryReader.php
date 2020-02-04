<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\File\Extension\SplFileInfo;
use MongoDB\BSON\UTCDateTime;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
class DirectoryReader extends AbstractReader
{
    /**
     * {@inheritDoc}
     *
     * @return Directory
     */
    public function read(SplFileInfo $node)
    {
        $directory = new Directory();

        $directory->guid = $this->slugifyPath($node->getRelativePathname());
        $directory->name = $node->getBasename();
        $directory->parent = $this->slugifyPath(dirname($node->getRelativePathname()));
        $directory->pathname = $node->getRelativePathname();
        $directory->ignored = $node->isIgnored();
        $directory->modified_date = new UTCDateTime($node->getMTime());
        $directory->indexed_date = new UTCDateTime();

        return $directory;
    }
}
