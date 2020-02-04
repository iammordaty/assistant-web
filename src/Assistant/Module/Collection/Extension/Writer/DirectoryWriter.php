<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use MongoDB\DeleteResult;

/**
 * Writer dla elementów będących katalogami
 */
class DirectoryWriter implements WriterInterface
{
    private DirectoryRepository $repository;

    public function __construct(DirectoryRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Zapisuje katalog zawierający utwory muzyczne w bazie danych
     *
     * @param Directory $directory
     * @return Directory
     */
    public function save($directory)
    {
        $this->repository->insert($directory);

        return $directory;
    }
}
