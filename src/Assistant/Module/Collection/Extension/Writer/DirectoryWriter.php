<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Model\CollectionItemInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;

/**
 * Writer dla elementów będących katalogami
 */
final class DirectoryWriter implements WriterInterface
{
    private DirectoryRepository $repository;

    public function __construct(DirectoryRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Zapisuje katalog zawierający utwory muzyczne w bazie danych
     *
     * @param Directory|CollectionItemInterface $collectionItem
     * @return Directory
     */
    public function save(CollectionItemInterface $collectionItem): Directory
    {
        $this->repository->save($collectionItem);

        return $collectionItem;
    }
}
