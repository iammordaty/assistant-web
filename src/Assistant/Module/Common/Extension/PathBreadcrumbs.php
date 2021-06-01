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

        $breadcrumbs = [];

        foreach ($parts as $part) {
            $guid = $this->slugify->slugify($part);
            $treeParts[] = $guid;

            // @todo: dodać url; pozwoli to na użycie klasy także w incoming (uelastycznia klasę)
            // @todo: pomyśleć nad przekształceniem tego w tablicę value object-ów

            $breadcrumbs[] = [
                'guid' => implode(DIRECTORY_SEPARATOR, $treeParts),
                'path' => $part,
            ];
        }

        return $breadcrumbs;
    }
}
