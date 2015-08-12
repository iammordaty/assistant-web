<?php

namespace Assistant\Module\Common\Repository;

/**
 * Klasa abstrakcyjna dla repozytoriów obiektów
 */
abstract class AbstractObjectRepository extends Repository
{
    /**
     * Klasa, na podstawie której zostanie utworzony obiekt
     *
     * @var string
     */
    protected static $model;

    /**
     * Kryteria, które muszą być spełnione, aby obiekt(-y) mógł zostać pobrany
     *
     * @var array
     */
    protected static $baseConditions;

    /**
     * Konstruktor
     *
     * @param \MongoDB $db
     * @throws \RuntimeException
     * @throws \BadMethodCallException
     */
    public function __construct(\MongoDB $db)
    {
        parent::__construct($db);

        if (empty(static::$model)) {
            throw new \RuntimeException('Parameter $model can not be empty.');
        }

        if (class_exists(static::$model) === false) {
            throw new \RuntimeException(sprintf('Class "%s" does not exists.', static::$model));
        }
    }

    /**
     * Zwraca obiekt na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $fields
     * @return mixed
     */
    public function findOneBy(array $conditions, array $fields = [])
    {
        $document = parent::findOneBy(
            array_merge($conditions, static::$baseConditions),
            $fields
        );

        if ($document === null) {
            return null;
        }

        return new static::$model($document);
    }

    /**
     * Zwraca obiekty na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @param array $fields
     * @param array $options
     * @return \Assistant\Module\Common\Model\AbstractModel[]
     */
    public function findBy(array $conditions, array $fields = [], array $options = [])
    {
        $documents = parent::findBy(
            array_merge($conditions, static::$baseConditions),
            $fields,
            $options
        );

        foreach ($documents as $document) {
            yield (new static::$model($document));
        }
    }

    /**
     * Dodaje obiekt do bazy danych
     *
     * @param \Assistant\Module\Common\Model\AbstractModel $object
     * @return bool
     */
    public function insert($object)
    {
        return parent::insert($object->toArray());
    }

    /**
     * Dodaje obiekt do bazy danych
     *
     * @param \Assistant\Module\Common\Model\AbstractModel $object
     * @return bool
     */
    public function update()
    {
        $object = func_get_arg(0);

        return parent::update(
            [ '_id' => $object->get('_id') ],
            $object->toArray()
        );
    }

    /**
     * Dodaje obiekt do bazy danych
     *
     * @param \Assistant\Module\Common\Model\AbstractModel $object
     * @return bool
     */
    public function remove($object)
    {
        return parent::removeById(
            $object->get('_id')
        );
    }
}
