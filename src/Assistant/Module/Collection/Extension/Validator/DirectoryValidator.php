<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;

/**
 * Walidator elementów będących katalogami
 */
final class DirectoryValidator implements ValidatorInterface
{
    private DirectoryRepository $repository;

    public function __construct(DirectoryRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Waliduje katalog pod kątem możliwości dodania go do bazy danych
     *
     * @param CollectionItemInterface $collectionItem
     * @return void
     *
     * @throws DuplicatedElementException
     */
    public function validate(CollectionItemInterface $collectionItem): void
    {
        /** @var Directory $directory */
        $directory = $collectionItem;

        $indexedDirectory = $this->repository->getByPathname($directory);

        if ($indexedDirectory !== null) {
            $message = sprintf('Directory "%s" is already in database.', $directory->getGuid());

            throw new DuplicatedElementException($message);
        }
    }
}
