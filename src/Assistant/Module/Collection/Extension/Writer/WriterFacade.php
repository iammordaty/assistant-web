<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Track\Model\Track;
use MongoDB;

/**
 * Fasada dla writerów zajmujących się zapisywaniem elementów w kolekcji
 */
class WriterFacade
{
    /**
     * @var MongoDB
     */
    private $db;

    /**
     * Lista obsługiwanych writerów danych
     *
     * @var array
     */
    private $writerNames = [
        'track',
        'directory',
    ];

    /**
     * Lista writerów danych
     *
     * @see setup()
     * @see $writerNames
     */
    private $writers = [ ];

    /**
     * Konstruktor
     *
     * @param MongoDB $db
     */
    public function __construct(MongoDB $db)
    {
        $this->db = $db;

        $this->setup();
    }

    /**
     * Zapisuje element kolekcji
     *
     * @param Track|Directory $item
     * @return Track|Directory
     */
    public function save($item)
    {
        return $this->writers[$this->getElementType($item)]->save($item);
    }

    /**
     * Usuwa elementy z kolekcji
     */
    public function clear()
    {
        foreach ($this->writers as $writer) {
            $writer->clean();
        }
    }

    /**
     * Przygotowuje writery do użycia
     */
    private function setup()
    {
        foreach ($this->writerNames as $writerName) {
            $className = sprintf('%s\%sWriter', __NAMESPACE__, ucfirst($writerName));

            $this->writers[$writerName] = new $className($this->db);

            unset($className, $writerName);
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
