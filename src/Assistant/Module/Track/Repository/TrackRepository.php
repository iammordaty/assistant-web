<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Repository\Query;
use Assistant\Module\Common\Repository\Storage;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Search\Extension\SearchCriteria;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Model\TrackDto;
use DateTime;
use MongoDB\BSON\Regex;
use MongoDB\Database;
use Traversable;

/**
 * Repozytorium obiektów Track
 *
 * @todo W niniejszej klasie niech zostanie tylko CRUD. Pozostałe metody (getOneBy[Field], getChildren, itp)
 *       przenieść do serwisu
 */
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
        $tracks = $this->findBy($query->toStorage(), $sort, $limit, $skip);

        return $tracks;
    }

    public function getOneByGuid(string|Regex $guid): ?Track
    {
        $track = $this->findOneBy([ 'guid' => $guid ]);

        return $track;
    }

    public function getOneByPathname(string $pathname): ?Track
    {
        $track = $this->findOneBy([ 'pathname' => $pathname ]);

        return $track;
    }

    /**
     * @param Directory $directory
     * @return Track[]|Traversable
     */
    public function getChildren(Directory $directory): array|Traversable
    {
        $directories = $this->findBy(
            [ 'parent' => $directory->getGuid() ],
            [ 'guid' => Storage::SORT_ASC ]
        );

        return $directories;
    }

    /**
     * @param DateTime|null $from
     * @param int|null $limit
     * @return Track[]|Traversable
     */
    public function getRecent(?DateTime $from = null, ?int $limit = null): array|Traversable
    {
        if (!$from) {
            $from = new DateTime();

            $from->modify('-3 years first day of january');
        }

        $tracks = $this->findBy(
            [ 'indexed_date' => [ '$gte' => Storage::toDateTime($from) ] ],
            [ 'indexed_date' => Storage::SORT_DESC ],
            $limit
        );

        return $tracks;
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

    public function countBy(SearchCriteria $searchCriteria): int
    {
        $criteria = Query::fromSearchCriteria($searchCriteria);
        $count = $this->count($criteria->toStorage());

        return $count;
    }

    /**
     * Zwraca informację o liczbie dokumentów w kolekcji na podstawie podanych kryteriów
     *
     * @deprecated Publiczna tymczasowo, ta metoda powinna być prywatna
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        return $this->storage->count($conditions);
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
