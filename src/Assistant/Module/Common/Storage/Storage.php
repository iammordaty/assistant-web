<?php

namespace Assistant\Module\Common\Storage;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Cursor;
use MongoDB\Model\BSONDocument;

// Persistence?
final class Storage
{
    public const SORT_ASC = 1;
    public const SORT_DESC = -1;
    public const SORT_TEXT_SCORE_DESC = [ 'sort' => [ '$meta' => 'textScore' ] ];

    private Collection $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * Zwraca dokument na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $options
     * @return BSONDocument|null
     */
    public function findOneBy(array $conditions, array $options = []): ?BSONDocument
    {
        $document = $this->collection->findOne(
            $conditions,
            $options
        );

        /** @var BSONDocument $document */
        return $document;
    }

    /**
     * Zwraca dokument na podstawie jego identyfikatora
     *
     * @param ObjectId|string $id
     * @param array $options
     * @return BSONDocument|null
     */
    public function findOneById($id, array $options = []): ?BSONDocument
    {
        return $this->findOneBy(
            [ '_id' => $this->idToObjectId($id) ],
            $options
        );
    }

    /**
     * Zwraca dokumenty na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $options
     * @return BSONDocument[]|Cursor
     */
    public function findBy(array $conditions, array $options = []): array|Cursor
    {
        return $this->collection->find(
            $conditions,
            $options
        );
    }

    /**
     * Zwraca dokumenty na podstawie listy identyfikatorów
     *
     * @param array $ids
     * @param array $fields
     * @return Cursor
     */
    public function findById(array $ids, array $fields = []): Cursor
    {
        return $this->findBy(
            [ '_id' => [ '$in' => $this->idsToObjectIds($ids) ] ],
            $fields
        );
    }

    /**
     * Dodaje dokument do bazy danych
     *
     * @param array $data
     * @return int
     */
    public function insert(array $data): int
    {
        $filtered = $this->filter($data);

        $result = $this->collection->insertOne($filtered);

        return $result->getInsertedCount();
    }

    /**
     * Aktualizuje dokument na podstawie przekazanych kryteriów
     *
     * @param array $conditions
     * @param array $data
     * @return int
     */
    public function update(array $conditions, array $data): int
    {
        if (array_key_exists('_id', $data) === true) {
            unset($data['_id']);
        }

        $filtered = $this->filter($data);

        $result = $this->collection
            ->updateOne(
                $conditions,
                [ '$set' => $filtered ]
            );

        return $result->getModifiedCount();
    }

    /**
     * Aktualizuje dokument na podstawie jego identyfikatora
     *
     * @param ObjectId|string $id
     * @param array $data
     * @return bool
     */
    public function updateById($id, array $data): bool
    {
        $result = $this->update(
            [ '_id' => $this->idToObjectId($id) ],
            $data
        );

        return $result === 1;
    }

    /**
     * Usuwa dokumenty na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @return int
     */
    public function removeBy(array $conditions = []): int
    {
        $result = $this->collection->deleteMany($conditions);

        return $result->getDeletedCount();
    }

    /**
     * Usuwa dokument o podanym identyfikatorze
     *
     * @param ObjectId|string $id
     * @return bool
     */
    public function removeById($id): bool
    {
        $result = $this->removeBy(
            [ '_id' => $this->idToObjectId($id) ]
        );

        return $result === 1;
    }

    /**
     * Zwraca informację o liczbie dokumentów w kolekcji na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        return $this->collection->countDocuments($conditions);
    }

    /**
     * Agreguje dane na podstawie podanych kryteriów
     *
     * @param array $pipeline
     * @param array $options
     * @return \Traversable
     */
    public function aggregate(array $pipeline, array $options = []): \Traversable
    {
        return $this->collection->aggregate($pipeline, $options);
    }

    /**
     * Tworzy indeks
     *
     * @param array $key
     * @param array $options
     * @return string
     */
    public function createIndex(array $key, array $options = []): string
    {
        return $this->collection->createIndex($key, $options);
    }

    public static function toDateTime(\DateTimeInterface $dateTime): UTCDateTime
    {
        $utcDateTime = new UTCDateTime($dateTime->getTimestamp() * 1000);

        return $utcDateTime;
    }

    /**
     * Konwertuje tablicę łańcuchów zawierający identyfikator Mongo do dokumentu ObjectId
     *
     * @param ObjectId[]|string[] $rawIds
     * @return ObjectId[]
     */
    private function idsToObjectIds(array $rawIds): array
    {
        $objectIds = [];

        foreach ($rawIds as $id) {
            $objectIds[] = $this->idToObjectId($id);
        }

        return $objectIds;
    }

    /**
     * Konwertuje łańcuch zawierający identyfikator Mongo do dokumentu ObjectId
     *
     * @param ObjectId|string $rawId
     * @return ObjectId
     */
    private function idToObjectId($rawId): ObjectId
    {
        return $rawId instanceof ObjectId ? $rawId : new ObjectId($rawId);
    }

    /**
     * Filtruje dane przed ich zapisem do bazy danych
     *
     * @param array $data
     * @return array
     */
    private function filter(array $data): array
    {
        return array_filter($data, fn($value) => $value !== null);
    }
}
