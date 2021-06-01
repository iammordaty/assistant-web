<?php

namespace Assistant\Module\Common\Extension;

use Cocur\Slugify\Slugify;

final class SlugifyService implements SlugifyInterface
{
    private Slugify $slugify;

    public function __construct(?SlugifyInterface $slugify = null)
    {
        $this->slugify = $slugify ?: new Slugify();
    }

    public function slugify(string $string, array $options = null): ?string
    {
        return $this->slugify->slugify($string, $options);
    }

    /** Zwraca ścieżkę do katalogu, w której poszczególne poziomy są slugiem (url friendly) */
    public function slugifyPath(string $path): ?string
    {
        $dirs = array_map(
            fn($dir) => $this->slugify->slugify($dir),
            array_filter(explode(DIRECTORY_SEPARATOR, $path))
        );

        return implode(DIRECTORY_SEPARATOR, $dirs) ?: null;
    }
}
