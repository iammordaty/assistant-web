<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Directory\Model\Directory;
use MongoDB\BSON\UTCDateTime;
use SplFileInfo;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
final class DirectoryReader extends AbstractReader
{
    /**
     * {@inheritDoc}
     *
     * @return Directory
     */
    public function read(SplFileInfo $node)
    {
        $directory = new Directory();

        $directory->guid = $this->slugifyPath($node->getPathname());
        $directory->name = $node->getBasename();
        $directory->parent = $this->slugifyPath(dirname($node->getPathname()));
        $directory->pathname = $node->getPathname();
        $directory->modified_date = new UTCDateTime($node->getMTime() * 1000);
        $directory->indexed_date = new UTCDateTime();

        return $directory;
    }
}
