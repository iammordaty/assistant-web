<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Model\ModelInterface;

/**
 * Interfejs dla klas zapisujących obiekty w kolekcji
 */
interface WriterInterface
{
    public function save(ModelInterface $element);
}
