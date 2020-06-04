<?php

namespace Assistant\Module\Common\Repository;

use Assistant\Module\Common\Model\ModelInterface;
use Assistant\Module\Common\Model\ModelInterface as Model;
use MongoDB\Database;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;

/**
 * Klasa abstrakcyjna dla repozytoriów obiektów
 */
abstract class AbstractObjectRepository extends AbstractRepository
{
    /**
     * Klasa, na podstawie której zostanie utworzony obiekt
     *
     * @var string
     */
    protected const MODEL = '';

    /**
     * Kryteria, które muszą być spełnione, aby obiekt(-y) mógł zostać pobrany
     *
     * @var array
     */
    protected static array $baseConditions = [];

    /**
     * Konstruktor
     *
     * @param Database $database
     * @throws \RuntimeException
     * @throws \BadMethodCallException
     */
    public function __construct(Database $database)
    {
        parent::__construct($database);

        if (empty(static::MODEL)) {
            throw new \RuntimeException('Constant MODEL can not be empty.');
        }

        if (class_exists(static::MODEL) === false) {
            throw new \RuntimeException(sprintf('Class "%s" does not exists.', static::MODEL));
        }
    }

    /**
     * Zwraca obiekt na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $options
     * @return Model|null
     */
    public function findOneBy(array $conditions, array $options = []): ?Model
    {
        $document = parent::findOneBy(
            array_merge($conditions, static::$baseConditions),
            $options
        );

        if ($document === null) {
            return null;
        }

        $objectClassname = static::MODEL;
        $object = new $objectClassname($document);

        return $object;
    }

    /**
     * Zwraca dokument na podstawie jego guid-a
     *
     * @param string $guid
     * @param array $options
     * @return Model|null
     */
    public function findOneByGuid(string $guid, array $options = []): ?Model
    {
        return $this->findOneBy(
            [ 'guid' => $guid ],
            $options
        );
    }

    /**
     * Zwraca obiekty na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $options
     * @return \Traversable|ModelInterface[]
     */
    public function findBy(array $conditions, array $options = []): \Traversable
    {
        $cursor = parent::findBy(
            array_merge($conditions, static::$baseConditions),
            $options
        );

        $cursor->setTypeMap([
            'root' => 'array',
            'document' => 'array',
            'array' => 'array',
        ]);

        /** @var ModelInterface $document */
        foreach ($cursor as $document) {
            $objectClassname = static::MODEL;
            $object = new $objectClassname($document);

            yield $object;
        }
    }

    /**
     * Dodaje obiekt do bazy danych
     *
     * @param Model $object
     * @return InsertOneResult
     */
    public function insert($object): InsertOneResult
    {
        return parent::insert($object->toArray());
    }

    /**
     * Dodaje obiekt do bazy danych
     *
     * @param Model $object
     * @return UpdateResult
     */
    public function update(): UpdateResult
    {
        $object = func_get_arg(0);

        return parent::update(
            [ '_id' => $object->get('_id') ],
            $object->toArray()
        );
    }

    /**
     * Usuwa obiekt z bazy danych
     *
     * @param Model $object
     * @return bool
     */
    public function remove($object)
    {
        return $this->removeById(
            $object->get('_id')
        );
    }
}
