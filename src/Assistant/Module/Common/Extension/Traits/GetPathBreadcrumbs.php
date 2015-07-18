<?php

namespace Assistant\Module\Common\Extension\Traits;

trait GetPathBreadcrumbs
{
    /**
     * Zwraca ścieżkę katalogu nadrzędnego w postaci breadcrubs-ów
     *
     * @param string $path
     * @return array|null
     */
    private function getPathBreadcrumbs($path)
    {
        if (strpos($path, DIRECTORY_SEPARATOR) === 0) {
            $path = ltrim($path, DIRECTORY_SEPARATOR);
        }

        if (empty($path)) {
            return [];
        }

        $slugify = new \Cocur\Slugify\Slugify;

        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $treeParts = [];

        $breadcrumbs = [];

        foreach ($parts as $part) {
            $guid = $slugify->slugify($part);
            $treeParts[] = $guid;

            $breadcrumbs[] = [
                'guid' => implode(DIRECTORY_SEPARATOR, $treeParts),
                'path' => $part,
            ];
        }

        return $breadcrumbs;
    }
}
