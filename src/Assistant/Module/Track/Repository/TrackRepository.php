<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Storage\Query;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Model\TrackDto;
use Generator;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;

/** Repozytorium obiektów Track */
final class TrackRepository
{
    private const string COLLECTION_NAME = 'tracks';

    private function __construct(private Storage $storage)
    {
    }

    public static function factory(Database $database): self
    {
        $collection = $database->selectCollection(self::COLLECTION_NAME);
        $storage = new Storage($collection);

        $repository = new self($storage);

        return $repository;
    }

    public function getOneBy(SearchCriteria $searchCriteria, ?SearchSort $searchSort = null): ?Track
    {
        $query = Query::fromSearchCriteria($searchCriteria);

        $document = $this->storage->findOneBy($query->toStorage(), options: [
            'sort' => $searchSort?->toStorage(),
        ]);

        if (!$document) {
            return null;
        }

        $track = self::createModel($document);

        return $track;
    }

    /** @return Generator<int, Track> */
    public function findBy(
        SearchCriteria $searchCriteria,
        ?SearchSort $searchSort = null,
        ?int $limit = null,
        ?int $skip = null,
    ): Generator {
        $query = Query::fromSearchCriteria($searchCriteria);

        $documents = $this->storage->findBy($query->toStorage(), options: [
            'sort' => $searchSort?->toStorage(),
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

    /** @return Generator<int, Track> */
    public function aggregate(array $pipeline): Generator
    {
        $documents = $this->storage->aggregate($pipeline);

        foreach ($documents as $document) {
            $track = self::createModel($document);

            yield $track;
        }
    }

    private static function createModel(BSONDocument $document): Track
    {
        $dto = TrackDto::fromStorage($document->bsonSerialize());
        $track = Track::fromDto($dto);

        return $track;
    }
}
