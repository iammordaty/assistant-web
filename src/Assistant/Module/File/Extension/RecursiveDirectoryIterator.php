<?php

namespace Assistant\Module\File\Extension;

/**
 * Iterator wspierający relatywne ścieżki do plików i katalogów
 */
class RecursiveDirectoryIterator extends \RecursiveDirectoryIterator
{
    /**
     * Zwraca biężący obiekt iteratora
     *
     * @return SplFileInfo
     */
    public function current()
    {
        return new SplFileInfo(
            parent::current()->getRealPath(),
            $this->getRealSubPathname()
        );
    }

    /**
     * Zwraca relatywną ścieżkę do katalogu nadrzędnego w postaci kanonicznej
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
