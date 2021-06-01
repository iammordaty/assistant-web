<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Directory\Model\Directory;
use SplFileInfo;

/**
 * Klasa, której zadaniem jest odczytywanie katalogów znajdujących się w kolekcji
 */
final class DirectoryReader implements ReaderInterface
{
    public function __construct(private SlugifyService $slugify)
    {
    }

    public function read(SplFileInfo $node): Directory
    {
        $modifiedTimestamp = (new \DateTime())->setTimestamp($node->getMTime());
        $indexedTimestamp = new \DateTime();

        $directory = new Directory(
            id:  null,
            guid: $this->slugify->slugifyPath($node->getPathname()),
            name: $node->getBasename(),
            parent: $this->slugify->slugifyPath($node->getPath()),
            pathname: $node->getPathname(),
            modifiedDate: $modifiedTimestamp,
            indexedDate: $indexedTimestamp,
        );

        return $directory;
    }
}
