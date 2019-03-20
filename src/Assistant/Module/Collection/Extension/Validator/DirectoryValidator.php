<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use MongoDB;

/**
 * Walidator elementów będących katalogami
 */
class DirectoryValidator extends AbstractValidator
{
    /**
     * @var DirectoryRepository
     */
    private $repository;

    /**
     * {@inheritDoc}
     */
    public function __construct(MongoDB $db, array $parameters)
    {
        parent::__construct($db, $parameters);

        $this->repository = new DirectoryRepository($db);
    }

    /**
     * Waliduje katalog pod kątem możliwości dodania go do bazy danych
     *
     * @param Directory $directory
     * @throws DuplicatedElementException
     */
    public function validate($directory)
    {
        $indexedDirectory = $this->repository->findOneBy([ 'pathname' => $directory->pathname ]);

        if ($indexedDirectory !== null) {
            throw new DuplicatedElementException(sprintf('Directory "%s" is already in database.', $directory->guid));
        }
    }
}
