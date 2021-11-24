<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Storage\Query;
use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Search\Extension\SearchCriteria;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Model\TrackDto;
use MongoDB\Database;
use Traversable;

/** Repozytorium obiektów Track */
final class TrackRepository
{
    private const COLLECTION_NAME = 'tracks';

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

    public function getOneBy(SearchCriteria $searchCriteria): ?Track
    {
        $query = Query::fromSearchCriteria($searchCriteria);
        $track = $this->findOneBy($query->toStorage());

        return $track;
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return Track[]|Traversable
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
            $track = self::createModel($document);

            yield $track;
        }
    }

    public function save(Track $track): bool
    {
        $dto = $track->toDto();

        if ($dto->getObjectId()) {
            $result = $this->storage->updateById($dto->getObjectId(), $dto->toStorage());
        } else {
            $result = $this->storage->insert($dto->toStorage());
        }

        return $result;
    }

    public function delete(Track $track): bool
    {
        $dto = $track->toDto();

        return $this->storage->removeById($dto->getObjectId());
    }

    /**
     * @deprecated Publiczna tymczasowo, ta metoda powinna być prywatna
     *
     * @param array $conditions
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return Track[]|Traversable
     */
    public function findBy(
        array $conditions,
        ?array $sort = [],
        ?int $limit = null,
        ?int $skip = null
    ): array|Traversable {
        $documents = $this->storage->findBy($conditions, options: [
            'sort' => $sort,
            'limit' => $limit,
            'skip' => $skip,
        ]);

        foreach ($documents as $document) {
            $track = self::createModel($document);

            yield $track;
        }
    }

    /**
     * Zwraca informację o liczbie dokumentów w kolekcji na podstawie podanych kryteriów
     *
     * @param SearchCriteria $searchCriteria
     * @return int
     */
    public function countBy(SearchCriteria $searchCriteria): int
    {
        $criteria = Query::fromSearchCriteria($searchCriteria);
        $count = $this->storage->count($criteria->toStorage());

        return $count;
    }

    private function findOneBy(array $conditions): ?Track
    {
        $document = $this->storage->findOneBy($conditions);

        if (!$document) {
            return null;
        }

        $track = self::createModel($document);

        return $track;
    }

    private static function createModel($document): Track
    {
        $dto = TrackDto::fromStorage($document->bsonSerialize());
        $track = Track::fromDto($dto);

        return $track;
    }
}
