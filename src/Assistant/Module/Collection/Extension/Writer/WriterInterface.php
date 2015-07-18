<?php

namespace Assistant\Module\Collection\Extension\Writer;

/**
 * Interfejs dla writerów zapisujących elementy do bazy danych
 */
interface WriterInterface
{
    /**
     * Zapisuje element kolekcji
     *
     * @param \Assistant\Module\Track\Model\Track|File\Model\Directory $element
     * @return \Assistant\Module\Track\Model\Track|File\Model\Directory
     */
    public function save($element);
}
