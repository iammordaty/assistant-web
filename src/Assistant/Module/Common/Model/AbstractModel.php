<?php

namespace Assistant\Module\Common\Model;

/**
 * Klasa abstrakcyjna dla modeli
 *
 * TODO: Niech modele nie zawierają powiązań specyficznych dla bazy danych (ObjectId, UTCDateTime, itd).
 *       Stworzyć klasę, która zajmie się konwertowaniem danych do/z bazy danych na typy PHP
 * TODO: Utworzyć gettery oraz settery i korzystać z nich zamiast metod magicznych
 */
abstract class AbstractModel implements ModelInterface
{
    /**
     * Konstruktor
     *
     * @param array $data
     */
    public function __construct($data = null)
    {
        if ($data !== null) {
            $this->set($data);
        }
    }

    /**
     * Ustawia jedną lub więcej wartość właściwości obiektu
     *
     * @deprecated Używać setterów
     * @param string|array $name
     * @param mixed $value
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public function set($name, $value = null)
    {
        $setProperty = function ($property, $value) {
            if (property_exists($this, $property) === false) {
                throw new \InvalidArgumentException(
                    sprintf('Class "%s" doesn\'t have property "%s".', static::class, $property)
                );
            }

            $this->$property = $value;
        };

        if (is_iterable($name)) {
            foreach ($name as $property => $value) {
                $setProperty($property, $value);
            }
        } else {
            $setProperty($name, $value);
        }

        return $this;
    }

    /**
     * Zwraca zadaną wartość właściwości lub wszystkie, jeśli nie podano nazwy właściwości
     *
     * @deprecated Używać getterów
     * @param string|null $name
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function get($name = null)
    {
        if (property_exists($this, $name) === false) {
            throw new \InvalidArgumentException(
                sprintf('Class "%s" doesn\'t have property "%s".', static::class, $name)
            );
        }

        return $this->$name;
    }

    /**
     * Zwraca tablicę asocjacyjną zawierającą dane obiektu
     *
     * @return array
     */
    public function toArray()
    {
        return get_object_vars($this);
    }

    /**
     * Magiczny setter
     *
     * @see set()
     * @param string|array $name
     * @param mixed $value
     *
     * @throws \InvalidArgumentException
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Magiczny getter
     *
     * @see get()
     * @param string $name
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Zwraca informację, czy właściwość istnieje w obiekcie
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return property_exists($this, $name);
    }
}
