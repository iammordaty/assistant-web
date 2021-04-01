<?php

namespace Assistant\Module\Directory\Repository;

use Assistant\Module\Common\Repository\Storage;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Model\DirectoryDto;
use MongoDB\Database;
use Traversable;

/**
 * Repozytorium obiektów Directory
 */
final class DirectoryRepository
{
    private const COLLECTION_NAME = 'directories';

    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public static function factory(Database $database): self
    {
        $collection = $database->selectCollection(self::COLLECTION_NAME);
        $storage = new Storage($collection);

        $repository = new self($storage);

        return $repository;
    }

    public function getByGuid(string $guid): ?Directory
    {
        $directory = $this->findOneBy([ 'guid' => $guid ]);

        return $directory;
    }

    public function getByPathname(Directory $directory): ?Directory
    {
        $directory = $this->findOneBy([ 'pathname' => $directory->getPathname() ]);

        return $directory;
    }

    /**
     * @param Directory $directory
     * @return Traversable|Directory[]
     */
    public function getChildren(Directory $directory): Traversable
    {
        $directories = $this->findBy(
            [ 'parent' => $directory->getGuid() ],
            [ 'guid' => Storage::SORT_ASC ]
        );

        return $directories;
    }

    public function save(Directory $directory): bool
    {
        $dto = $directory->toDto();

        if ($dto->getObjectId()) {
            $result = $this->storage->updateById($dto->getObjectId(), $dto->toStorage());
        } else {
            $result = $this->storage->insert($dto->toStorage());
        }

        return $result;
    }

    public function delete(Directory $directory): bool
    {
        $dto = $directory->toDto();

        return $this->storage->removeById($dto->getObjectId());
    }

    /**
     * @deprecated Publiczna tymczasowo, ta metoda powinna być prywatna
     *
     * @param array $conditions
     * @param array|null $sort
     * @return Traversable|Directory[]
     */
    public function findBy(array $conditions, ?array $sort = []): Traversable
    {
        $options = [];

        if ($sort) {
            $options['sort'] = $sort;
        }

        $documents = $this->storage->findBy($conditions, $options);

        foreach ($documents as $document) {
            $directory = self::createModel($document);

            yield $directory;
        }
    }

    private function findOneBy(array $conditions): ?Directory
    {
        $document = $this->storage->findOneBy($conditions);

        if (!$document) {
            return null;
        }

        $directory = self::createModel($document);

        return $directory;
    }

    private static function createModel($document): Directory
    {
        $dto = DirectoryDto::fromStorage($document->bsonSerialize());
        $directory = Directory::fromDto($dto);

        return $directory;
    }
}
