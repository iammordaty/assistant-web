<?php

namespace Assistant;

/**
 * Klasa abstrakcyjna dla modeli
 */
class Model
{
    /**
     * Konstruktor
     *
     * @param array $data
     */
    public function __construct($data = null)
    {
        if ($data !== null) {
            $this->setData($data);
        }
    }

    /**
     * Wypełnia model przekazanymi danymi
     *
     * @param type $data
     * @return self
     */
    public function setData($data)
    {
        if (is_object($data) && !($data instanceof \Traversable)) {
            $data = get_object_vars($data);
        }

        foreach ($data as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }

        return $this;
    }
}
