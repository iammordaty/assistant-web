<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\File;

/**
 * Fasada dla procesorów przetwarzających elementy znajdujące się w kolekcji
*/
class ReaderFacade
{
    /**
     * Lista obsługiwanych procesorów danych
     *
     * @var array
     */
    private $readerNames = [
        'file',
        'dir',
    ];

    /**
     * Lista procesorów danych
     *
     * @see setup()
     * @see $readerNames
     */
    private $readers = [ ];

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
        return $this->readers[$node->getType()]->process($node);
    }

    /**
     * Przygotowuje procesory do użycia
     */
    protected function setup()
    {
        foreach ($this->readerNames as $readerName) {
            $className = sprintf('%s\%sReader', __NAMESPACE__, ucfirst($readerName));

            $this->readers[$readerName] = new $className($this->parameters);

            unset($className, $readerName);
        }
    }
}
