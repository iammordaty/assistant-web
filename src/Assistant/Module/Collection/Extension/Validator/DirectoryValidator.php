<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Directory\Model\Directory;

/** Walidator elementów będących katalogami */
final class DirectoryValidator implements ValidatorInterface
{
    public function __construct(private DirectoryService $directoryService)
    {
    }

    /** Waliduje katalog pod kątem możliwości dodania go do bazy danych */
    public function validate(CollectionItemInterface $collectionItem): void
    {
        /** @var Directory $directory */
        $directory = $collectionItem;

        $indexedDirectory = $this->directoryService->getByPathname($directory->getPathname());

        if ($indexedDirectory !== null) {
            $message = sprintf('Directory "%s" is already in database.', $directory->getGuid());

            throw new DuplicatedElementException($message);
        }
    }
}
