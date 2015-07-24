<?php

namespace Assistant\Module\Collection\Extension\Processor;

use Assistant\Module\File;

/**
 * Interfejs dla procesorów elementów znajdujących się w kolekcji
 */
interface ProcessorInterface
{
    /**
     * Przetwarza surowy element kolekcji
     *
     * @param File\Extension\SplFileInfo
     * @return \Assistant\Module\Track\Model\Track|\Assistant\Module\Directory\Model\Directory
     */
    public function process(File\Extension\SplFileInfo $node);
}
