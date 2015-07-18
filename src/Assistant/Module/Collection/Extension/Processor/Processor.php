<?php

namespace Assistant\Module\Collection\Extension\Processor;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
class Processor implements ProcessorInterface
{
    /**
     * @var array
     */
    protected $processors = [
        'file',
        'directory',
    ];

    /**
     * @var array
     */
    protected $parameters;

    /**
     * Konstruktor
     *
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;

        $this->setup();
    }

    /**
     * {@inheritDoc}
     */
    public function process($node)
    {
        return $this->{ lcfirst($node->getType()) }->process($node);
    }

    protected function setup()
    {
        foreach ($this->processors as $processor) {
            $className = sprintf('%s\%sProcessor', __NAMESPACE__, ucfirst($processor));

            $this->{ lcfirst($processor) } = new $className($this->parameters);
        }
    }
}
