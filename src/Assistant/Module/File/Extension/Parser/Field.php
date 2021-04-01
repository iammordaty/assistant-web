<?php

namespace Assistant\Module\File\Extension\Parser;

abstract class Field
{
    protected array $parameters;

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
     * @return string|int|array
     */
    abstract public function parse(string $value);

    /**
     * Przygotowuje parser do użycia
     */
    abstract protected function setup(): void;
}
