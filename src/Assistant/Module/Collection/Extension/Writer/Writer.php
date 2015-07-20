<?php

namespace Assistant\Module\Collection\Extension\Writer;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
class Writer implements WriterInterface
{
    /**
     * @var \MongoDB
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
     *
     * @var \Assistant\Module\Collection\Extension\Writer[]
     */
    private $writers = [ ];

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
     * @param \Assistant\Module\Track\Model\Track|\Assistant\Module\Directory\Model\Directory $item
     * @return \Assistant\Module\Track\Model\Track|\Assistant\Module\Directory\Model\Directory
     */
    public function save($item)
    {
        return $this->writers[$this->getElementType($item)]->save($item);
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
     * @param \Assistant\Module\Track\Model\Track|\Assistant\Module\Directory\Model\Directory $item
     * @return string
     */
    private function getElementType($item)
    {
        $parts = explode('\\', get_class($item));

        return lcfirst(array_pop($parts));
    }
}
