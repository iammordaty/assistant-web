<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Common\Model\ModelInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;

/**
 * Walidator elementów będących katalogami
 */
class DirectoryValidator implements ValidatorInterface
{
    private DirectoryRepository $repository;

    public function __construct(DirectoryRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Waliduje katalog pod kątem możliwości dodania go do bazy danych
     *
     * @param Directory|ModelInterface $directory
     * @return void
     * @throws DuplicatedElementException
     */
    public function validate(ModelInterface $directory): void
    {
        $indexedDirectory = $this->repository->findOneBy([ 'pathname' => $directory->pathname ]);

        if ($indexedDirectory !== null) {
            throw new DuplicatedElementException(sprintf('Directory "%s" is already in database.', $directory->guid));
        }
    }
}
