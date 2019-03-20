<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use MongoDB;

/**
 * Writer dla elementów będących katalogami
 */
class DirectoryWriter extends AbstractWriter
{
    /**
     * {@inheritDoc}
     */
    public function __construct(MongoDB $db)
    {
        parent::__construct($db);

        $this->repository = new DirectoryRepository($db);
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

    /**
     * Usuwa elementy znajdujące się w kolekcji
     *
     * @return int
     */
    public function clean()
    {
        return $this->repository->removeBy();
    }
}
