<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Collection\Model\CollectionItemInterface;

/**
 * Interfejs dla klas zapisujących obiekty w kolekcji
 */
interface WriterInterface
{
    public function save(CollectionItemInterface $collectionItem): CollectionItemInterface;
}
