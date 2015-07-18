<?php

namespace Assistant\Module\Collection\Extension\Processor;

use Assistant\Module\Collection;
use Assistant\Module\Directory;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
class DirectoryProcessor extends Collection\Extension\Processor implements ProcessorInterface
{
    /**
     * Przetwarza katalog znajdujący się w kolekcji
     *
     * @param \Assistant\Module\File\Extension\Node\Directory $node
     * @return Directory\Model\Directory
     */
    public function process($node)
    {
        $directory = new Directory\Model\Directory();

        $directory->guid = $this->slugifyPath($node->getRelativePathname());
        $directory->name = $node->getBasename();
        $directory->parent = $this->slugifyPath(dirname($node->getRelativePathname()));
        $directory->pathname = $node->getRelativePathname();
        $directory->ignored = $node->isIgnored();
        $directory->indexed_date = new \MongoDate();

        return $directory;
    }
}
