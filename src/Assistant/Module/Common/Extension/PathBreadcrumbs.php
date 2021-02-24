<?php

namespace Assistant\Module\Common\Extension;

use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;

final class PathBreadcrumbs
{
    private SlugifyInterface $slugify;

    public function __construct(SlugifyInterface $slugify)
    {
        $this->slugify = $slugify;
    }

    public static function factory(): PathBreadcrumbs
    {
        $slugify = new Slugify();

        return new self($slugify);
    }

    /**
     * Zwraca ścieżkę katalogu nadrzędnego w postaci breadcrumbs-ów
     *
     * @param string $path
     * @return array
     */
    public function get(string $path): array
    {
        if (strpos($path, DIRECTORY_SEPARATOR) === 0) {
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
