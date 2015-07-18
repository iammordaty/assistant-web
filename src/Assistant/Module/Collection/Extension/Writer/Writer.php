<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\File;
use Assistant\Module\Collection;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
class Writer implements WriterInterface
{
    /**
     * @var array
     */
    private $writers = [
        'track',
        'directory',
    ];

    /**
     * @var \MongoDB
    */
    private $db;

    /**
     * Konstruktor
     *
     * @param \MongoDB $db
     */
    public function __construct(\MongoDB $db)
    {
        $this->db = $db;

        $this->setup();
    }

    /**
     * Zapisuje element kolekcji
     *
     * @param \Assistant\Module\Track\Model\Track|File\Model\Directory $element
     * @return \Assistant\Module\Track\Model\Track|File\Model\Directory
     */
    public function save($element)
    {
        return $this->{ $this->getElementType($element) }->save($element);
    }

    private function setup()
    {
        foreach ($this->writers as $writer) {
            $className = sprintf('%s\%sWriter', __NAMESPACE__, ucfirst($writer));

            $this->{ lcfirst($writer) } = new $className($this->db);
        }
    }

    /**
     * Zwraca typ podanego elementu kolekcji
     *
     * @param \Assistant\Module\Track\Model\Track|File\Model\Directory $element
     * @return string
     */
    private function getElementType(\Assistant\Model $element)
    {
        $parts = explode('\\', get_class($element));

        return lcfirst(array_pop($parts));
    }
}
