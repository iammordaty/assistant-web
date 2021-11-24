<?php

namespace Assistant\Module\Directory\Repository;

use Assistant\Module\Common\Storage\Query;
use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Model\DirectoryDto;
use Assistant\Module\Search\Extension\SearchCriteria;
use MongoDB\Database;
use Traversable;

/** Repozytorium obiektów Directory */
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

    public function getOneBy(SearchCriteria $searchCriteria): ?Directory
    {
        $query = Query::fromSearchCriteria($searchCriteria);
        $directory = $this->findOneBy($query->toStorage());

        return $directory;
    }

    public function getByPathname(Directory $directory): ?Directory
    {
        $directory = $this->findOneBy([ 'pathname' => $directory->getPathname() ]);

        return $directory;
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return Directory[]|Traversable
     */
    public function getBy(
        SearchCriteria $searchCriteria,
        ?array $sort = null,
        ?int $limit = null,
        ?int $skip = null
    ): array|Traversable {
        $query = Query::fromSearchCriteria($searchCriteria);

        $documents = $this->storage->findBy($query->toStorage(), options: [
            'sort' => $sort,
            'limit' => $limit,
            'skip' => $skip,
        ]);

        foreach ($documents as $document) {
            $directory = self::createModel($document);

            yield $directory;
        }
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
