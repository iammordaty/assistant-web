<?php

namespace Assistant\Module\File\Extension\Traits;

trait SearchPathTrait
{
    /**
     * Zwraca informację, czy ścieżka znajduje się na podanej liście ścieżek
     *
     * @param string $path
     * @param array $paths
     * @return bool
     */
    private function searchPath($path, $paths)
    {
        $isFound = false;

        foreach ($paths as $excludedPath) {
            if (strpos($path, $excludedPath) !== false) {
                $isFound = true;
                break;
            }
        }

        return $isFound;
    }
}
