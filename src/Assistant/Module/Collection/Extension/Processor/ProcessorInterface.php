<?php

namespace Assistant\Module\Collection\Extension\Processor;

/**
 * Interfejs dla procesorów elementów znajdujących się w kolekcji
 */
interface ProcessorInterface
{
    /**
     * Przetwarza surowy element kolekcji
     *
     * @param \Assistant\Module\File\Extension\Node\File|\Assistant\Module\File\Extension\Node\Directory $node
     * @return \Assistant\Module\Track\Model\Track|\Assistant\Module\File\Model\Directory
     */
    public function process($node);
}
