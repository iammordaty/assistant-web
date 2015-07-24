<?php

namespace Assistant;

/**
 * Klasa abstrakcyjna dla repozytoriów
 */
abstract class Repository
{
    /**
     * Nazwa kolekcji, na której operuje repozytorium
     *
     * @var string
     */
    protected static $collection;

    /**
     * Nazwa klasy, na podstawie której zostanie utworzony obiekt.
     *
     * @var string
     */
    protected static $model;

    /**
     * Kryteria, które muszą być spełnione, aby obiekt(-y) mógł zostać pobrany
     *
     * @var array
     */
    protected static $baseConditions = [];

    /**
     * Obiekt klasy MongoDB
     *
     * @var MongoDB
     */
    protected $db;

    /**
     * Konstruktor
     *
     * @param \MongoDB $db
     * @throws \RuntimeException
     */
    public function __construct(\MongoDB $db)
    {
        $this->db = $db;

        if (empty(static::$collection)) {
            throw new \RuntimeException('Parameter $collection can not be empty.');
        }

        if (empty(static::$model)) {
            throw new \RuntimeException('Parameter $model can not be empty.');
        }
    }

    /**
     * Zwraca dokument na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $fields
     * @return Model|null
     */
    public function findOneBy(array $conditions, array $fields = [])
    {
        $document = $this->db
            ->selectCollection(static::$collection)
            ->findOne(
                $conditions,
                $fields
            );

        if ($document === null) {
            return null;
        }

        return new static::$model($document);
    }

    /**
     * Zwraca dokument na podstawie jego guid-a
     *
     * @param string $guid
     * @param array $fields
     * @return Model|null
     */
    public function findOneByGuid($guid, array $fields = [])
    {
        return $this->findOneBy(
            [ 'guid' => $guid ],
            $fields
        );
    }

    /**
     * Zwraca dokument na podstawie jego identyfikatora
     *
     * @param \MongoId $id
     * @param array $fields
     * @return Model|null
     */
    public function findOneById($id, array $fields = [])
    {
        return $this->findOneBy(
            [ '_id' => $this->idToMongoId($id) ],
            $fields
        );
    }

    /**
     * Zwraca obiekty na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $fields
     * @param array $options
     * @return Model[]
     */
    public function findBy(array $conditions, array $fields = [], array $options = [])
    {
        $documents = $this->db
            ->selectCollection(static::$collection)
            ->find(
                $conditions,
                $fields
            );

        if (isset($options['sort'])) {
            $documents->sort($options['sort']);
        }
        if (isset($options['limit'])) {
            $documents->limit($options['limit']);
        }
        if (isset($options['skip'])) {
            $documents->skip($options['skip']);
        }

        // An iteration on a MongoCursor object with yield produces a segfault
        // https://bugs.php.net/bug.php?id=66671

        $objects = [];

        foreach ($documents as $document) {
            $objects[] = (new static::$model($document));
        }

        return $objects;
    }

    /**
     * Zwraca obiekty na podstawie listy identyfikatorów
     *
     * @param array $ids
     * @param array $fields
     * @return Model[]
     */
    public function findById(array $ids, array $fields = [])
    {
        return $this->findBy(
            [ '_id' => [ '$in' => $this->idsToMongoIds($ids) ] ],
            $fields
        );
    }

    /**
     * Dodaje obiekt do bazy danych
     *
     * @param array $data
     * @return array
     */
    public function insert(array $data)
    {
        if (array_key_exists('_id', $data) === true) {
            $data['_id'] = $this->idToMongoId($data['_id']);
        } else {
            $data['_id'] = new \MongoId();
        }

        $result = $this->db
            ->selectCollection(static::$collection)
            ->insert($data);

        return ((int) $result['ok'] === 1);
    }
    
    /**
     * Aktualizuje obiekt na podstawie przekazanych kryteriów
     *
     * @param array $conditions
     * @param array $data
     * @return int Liczba zaktualizowanych obiektów
     */
    public function update(array $conditions, array $data)
    {
        if (array_key_exists('_id', $data) === true) {
            unset($data['_id']);
        }

        $result = $this->db
            ->selectCollection(static::$collection)
            ->update(
                $conditions,
                [ '$set' => $data ]
            );

        return $result['n'];
    }

    /**
     * Aktualizuje obiekt na podstawie jego identyfikatora
     *
     * @param \MongoId|string $id
     * @param array $data
     * @return bool
     */
    public function updateById($id, array $data)
    {
        return (bool) $this->update(
            [ '_id' => $this->idToMongoId($id) ],
            $data
        );
    }

    /**
     * Usuwa obiekty na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @return int Liczba usuniętych obiektów
     */
    public function removeBy(array $conditions = [])
    {
        $result = $this->db
            ->selectCollection(static::$collection)
            ->remove(
                $conditions
            );

        return $result['n'];
    }

    /**
     * Usuwa obiekt o podanym identyfikatorze
     *
     * @param \MongoId|string $id
     * @return bool
     */
    public function removeById($id)
    {
        return (bool) $this->removeBy(
            [ '_id' => $this->idToMongoId($id) ]
        );
    }

    /**
     * Zwraca informację o liczbie obiektów w kolekcji na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = [])
    {
        return $this->db
            ->selectCollection(static::$collection)
            ->count(
                $conditions
            );
    }

    /**
     * Wywołuje zadany kod po stronie serwera
     *
     * @param string $code
     * @param array $data
     * @return mixed
     */
    public function execute($code, array $data = [])
    {
        $result = $this->db
            ->execute(
                new \MongoCode($code, $data)
            );

        return ((int) $result['ok'] === 1) ? $result : false;
    }

    /**
     * Tworzy indeks
     *
     * @param array $keys
     * @param array $options
     * @return mixed
     */
    public function createIndex(array $keys, array $options = [])
    {
        return $this->db
            ->selectCollection(static::$collection)
            ->createIndex($keys, $options);
    }

    /**
     * Konwertuje tablicę łańcuchów zawierający identyfikator Mongo do obiektu \MongoId
     *
     * @param string $ids
     * @return \MongoId[]
     */
    protected function idsToMongoIds(array $ids)
    {
        $mongoIds = [];

        foreach ($ids as $id) {
            $mongoIds[] = $this->idToMongoId($id);
        }

        return $mongoIds;
    }

    /**
     * Konwertuje łańcuch zawierający identyfikator Mongo do obiektu \MongoId
     *
     * @param string $id
     * @return \MongoId
     */
    protected function idToMongoId($id)
    {
        return ($id instanceof \MongoId ? $id : new \MongoId($id));
    }
}
