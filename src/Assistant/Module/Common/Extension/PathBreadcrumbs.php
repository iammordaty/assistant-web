<?php

namespace Assistant\Module\Common\Extension;

// a może Breadcrumbs?
final class PathBreadcrumbs
{
    private SlugifyService $slugify;

    public function __construct(SlugifyService $slugify)
    {
        $this->slugify = $slugify;
    }

    /**
     * Zwraca ścieżkę katalogu nadrzędnego w postaci breadcrumbs-ów
     *
     * @param string $path
     * @return array
     */
    public function get(string $path): array
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = ltrim($path, DIRECTORY_SEPARATOR);
        }

        if (empty($path)) {
            return [];
        }

        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $treeParts = [];
        $treePathname = [];

        $breadcrumbs = [];

        foreach ($parts as $part) {
            $treeParts[] = $this->slugify->slugify($part);
            $treePathname[] = $part;

            // @todo: dodać url; pozwoli to na użycie klasy także w incoming (uelastycznia klasę)
            // @todo: pomyśleć nad przekształceniem tego w tablicę value object-ów
            // @todo: ogarnąć to, bo bałagan zrobił się pierwszorzędny :-)

            $breadcrumbs[] = [
                'guid' => implode(DIRECTORY_SEPARATOR, $treeParts),
                'path' => $part,
                'pathname' => DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $treePathname),
            ];
        }

        return $breadcrumbs;
    }
}
