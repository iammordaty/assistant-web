<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Collection;
use Assistant\Module\Directory;

/**
 * Writer dla elementów będących katalogami
 */
class DirectoryWriter extends Collection\Extension\Writer implements WriterInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct(\MongoDB $db)
    {
        parent::__construct($db);

        $this->repository = new Directory\Repository\DirectoryRepository($db);
    }

    /**
     * Zapisuje katalog zawierający utwory muzyczne w bazie danych
     *
     * @param \Assistant\Module\Directory\Model\Directory $directory
     * @return \Assistant\Module\Directory\Model\Directory
     */
    public function save($directory)
    {
        $indexedDirectory = $this->repository->findOneBy([ 'pathname' => $directory->pathname ], [ 'metadata_md5' ]);

        if ($indexedDirectory !== null) {
            throw new Exception\DuplicatedElementException(
                sprintf('Directory "%s" is already in database.', $directory->guid)
            );
        }

        $this->repository->insert((array) $directory);

        return $directory;
    }
}
