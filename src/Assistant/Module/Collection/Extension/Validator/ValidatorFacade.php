<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Track\Model\Track;
use MongoDB;

/**
 * Fasada dla walidatorów plików oraz katalogów mających
 * zostać dodanych do kolekji
 */
class ValidatorFacade
{
    /**
     * Lista obsługiwanych walidatorów danych
     *
     * @var array
     */
    private $validatorNames = [
        'track',
        'directory',
    ];

    /**
     * Lista walidatorów danych
     *
     * @see setup()
     * @see $validatorNames
     */
    private $validators = [ ];

    /**
     * @var MongoDB
     */
    private $db;

    /**
     * @var array
     */
    private $parameters;

    /**
     * Konstruktor
     *
     * @param MongoDB $db
     * @param array $parameters
     */
    public function __construct(MongoDB $db, array $parameters)
    {
        $this->db = $db;
        $this->parameters = $parameters;

        $this->setup();
    }

    /**
     * @param Track|Directory $item
     * @return Track|Directory
     */
    public function validate($item)
    {
        return $this->validators[$this->getElementType($item)]->validate($item);
    }

    /**
     * Przygotowuje walidatory do użycia
     */
    protected function setup()
    {
        // TODO: jak wyżej
        // TODO: przekazywać repo, nie bazę danych
        foreach ($this->validatorNames as $validatorName) {
            $className = sprintf('%s\%sValidator', __NAMESPACE__, ucfirst($validatorName));

            $this->validators[$validatorName] = new $className($this->db, $this->parameters);

            unset($className, $validatorName);
        }
    }

    /**
     * Zwraca typ podanego elementu kolekcji
     *
     * @param Track|Directory $item
     * @return string
     */
    private function getElementType($item)
    {
        $parts = explode('\\', get_class($item));

        return lcfirst(array_pop($parts));
    }
}
