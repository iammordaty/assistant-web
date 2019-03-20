<?php

namespace Assistant\Module\Collection\Extension\Validator;

use MongoDB;

/**
 * Klasa abstrakcyjna dla walidatorów
 */
abstract class AbstractValidator
{
    /**
     * @var MongoDB
     */
    protected $db;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param MongoDB $db
     * @param array $parameters
     */
    public function __construct(MongoDB $db, array $parameters)
    {
        $this->db = $db;
        $this->parameters = $parameters;
    }

    abstract public function validate($track);
}
