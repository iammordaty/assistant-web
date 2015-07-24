<?php

namespace Assistant\Module\Collection\Extension\Processor;

use Assistant\Module\File;

/**
 * Fasada dla procesorów przetwarzających elementy znajdujące się w kolekcji
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
        'dir',
    ];

    /**
     * Lista procesorów danych
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
    public function process(File\Extension\SplFileInfo $node)
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
