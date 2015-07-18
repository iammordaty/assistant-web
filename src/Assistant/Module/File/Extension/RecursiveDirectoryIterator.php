<?php

namespace Assistant\Module\File\Extension;

/**
 * Iterator wspierający relatywne ścieżki do plików i katalogów
 */
class RecursiveDirectoryIterator extends \RecursiveDirectoryIterator
{
    /**
     * @var array
     */
    private $nodeTypeToClassNameMap = [
        'file' => Node\File::class,
        'dir' => Node\Directory::class,
    ];

    /**
     * Zwraca biężący obiekt iteratora
     *
     * @return Node\Directory|Node\File
     */
    public function current()
    {
        /* @var $current \SplFileInfo */
        $current = parent::current();

        $className = $this->nodeTypeToClassNameMap[$current->getType()];

        return new $className(
            $current->getRealPath(),
            $this->getRealSubPathname()
        );
    }

    /**
     * Zwraca absolutną ścieżkę do katalogu nadrzędnego w postaci kanonicznej
     *
     * @return string
     */
    private function getRealSubPathname()
    {
        $parts = explode(DIRECTORY_SEPARATOR, ltrim($this->getRealPath(), DIRECTORY_SEPARATOR));

        array_shift($parts);

        return implode(DIRECTORY_SEPARATOR, $parts);
    }
}
