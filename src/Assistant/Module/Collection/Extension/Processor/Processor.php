<?php

namespace Assistant\Module\Collection\Extension\Processor;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
class Processor implements ProcessorInterface
{
    /**
     * Lista obsługiwanych procesorów danych
     *
     * @var array
     */
    private $processorNames = [
        'file',
        'directory',
    ];

    /**
     * Lista procesoró danych
     *
     * @see setup()
     * @see $processorNames
     *
     * @var \Assistant\Module\Collection\Extension\Processor[]
     */
    private $processors = [ ];

    /**
     * @var array
     */
    private $parameters;

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
        return $this->processors[$node->getType()]->process($node);
    }

    /**
     * Przygotowuje procesory do użycia
     */
    protected function setup()
    {
        foreach ($this->processorNames as $processorName) {
            $className = sprintf('%s\%sProcessor', __NAMESPACE__, ucfirst($processorName));

            $this->processors[$processorName] = new $className($this->parameters);

            unset($className, $processorName);
        }
    }
}
