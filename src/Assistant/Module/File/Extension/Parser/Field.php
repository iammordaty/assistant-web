<?php

namespace Assistant\Module\File\Extension\Parser;

abstract class Field
{
    /**
     * @var array
     */
    protected $parameters;

    /**
     * Konstruktor
     *
     * @param array|null $parameters
     */
    public function __construct(array $parameters = null)
    {
        if ($parameters !== null) {
            $this->parameters = $parameters;
        }

        $this->setup();
    }

    /**
     * Parsuje wartość tagu
     *
     * @param string $value
     * @return string|integer|array
     */
    abstract public function parse($value);

    /**
     * Zwraca typ tagu
     *
     * @return string
     */
    public function getType()
    {
        $parts = explode('\\', static::class);

        return strtolower(array_pop($parts));
    }

    protected function setup()
    {

    }
}
