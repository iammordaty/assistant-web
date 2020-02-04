<?php

namespace Assistant\Module\Common\Repository;

use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\BSON\ObjectId;
use MongoDB\DeleteResult;
use MongoDB\Driver\Cursor;
use MongoDB\InsertOneResult;
use MongoDB\Model\BSONDocument;
use MongoDB\UpdateResult;

/**
 * Bazowa klasa dla repozytoriów
 */
abstract class AbstractRepository
{
    /**
     * Nazwa kolekcji, na której operuje repozytorium
     *
     * @var string
     */
    protected const COLLECTION = '';

    /**
     * Obiekt kolekcji, na której operuje repozytorium
     *
     * @var Collection
     */
    protected Collection $collection;

    /**
     * Konstruktor
     *
     * @param Database $database
     * @throws \RuntimeException
     */
    public function __construct(Database $database)
    {
        // TODO: Przyjmować Collection, dodać metodę factory(Database $database)

        if (empty(static::COLLECTION)) {
            throw new \RuntimeException('Constant COLLECTION can not be empty.');
        }

        $this->collection = $database->selectCollection(static::COLLECTION);
    }

    /**
     * Zwraca dokument na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $options
     * @return BSONDocument|null
     */
    public function findOneBy(array $conditions, array $options = [])
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
    public function findOneById($id, array $options = [])
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
     * @return Cursor
     */
    public function findBy(array $conditions, array $options = [])
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
    public function findById(array $ids, array $fields = [])
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
     * @return InsertOneResult
     */
    public function insert($data): InsertOneResult
    {
        $filtered = $this->filter($data);

        return $this->collection->insertOne($filtered);
    }
    
    /**
     * Aktualizuje dokument na podstawie przekazanych kryteriów
     *
     * @param array $conditions
     * @param array $data
     * @return UpdateResult
     */
    public function update(): UpdateResult
    {
        [ $conditions, $data ] = func_get_args();

        if (array_key_exists('_id', $data) === true) {
            unset($data['_id']);
        }

        $filtered = $this->filter($data);

        return $this->collection
            ->updateOne(
                $conditions,
                [ '$set' => $filtered ]
            );
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

        return $result->getModifiedCount() === 1;
    }

    /**
     * Usuwa dokumenty na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @return DeleteResult
     */
    public function removeBy(array $conditions = []): DeleteResult
    {
        return $this->collection->deleteMany($conditions);
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

        return $result->getDeletedCount() === 1;
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
