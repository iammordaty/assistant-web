<?php

namespace Assistant\Module\Directory\Repository;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Model\DirectoryDto;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Storage\Query;
use Generator;
use MongoDB\Database;

/** Repozytorium obiektów Directory */
final class DirectoryRepository
{
    private const string COLLECTION_NAME = 'directories';

    public function __construct(private Storage $storage)
    {
    }

    public static function factory(Database $database): self
    {
        $collection = $database->selectCollection(self::COLLECTION_NAME);
        $storage = new Storage($collection);

        $repository = new self($storage);

        return $repository;
    }

    public function getOneBy(SearchCriteria $searchCriteria, ?SearchSort $searchSort = null): ?Directory
    {
        $query = Query::fromSearchCriteria($searchCriteria);

        $document = $this->storage->findOneBy($query->toStorage(), options: [
            'sort' => $searchSort?->toStorage(),
        ]);

        if (!$document) {
            return null;
        }

        return self::createModel($document);
    }

    /** @return Generator<int, Directory> */
    public function getBy(
        SearchCriteria $searchCriteria,
        ?SearchSort $searchSort = null,
        ?int $limit = null,
        ?int $skip = null
    ): Generator {
        $query = Query::fromSearchCriteria($searchCriteria);

        $documents = $this->storage->findBy($query->toStorage(), options: [
            'sort' => $searchSort?->toStorage(),
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
