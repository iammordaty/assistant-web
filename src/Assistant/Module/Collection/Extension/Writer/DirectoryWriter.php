<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Directory\Model\Directory;

/** Writer dla elementów będących katalogami */
final class DirectoryWriter implements WriterInterface
{
    public function __construct(private DirectoryService $directoryService)
    {
    }

    /** Zapisuje katalog zawierający utwory muzyczne w bazie danych */
    public function save(Directory|CollectionItemInterface $collectionItem): Directory
    {
        $this->directoryService->save($collectionItem);

        return $collectionItem;
    }
}
