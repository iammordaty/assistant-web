<?php

namespace Assistant\Module\Common\Extension;

use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;

final class SlugifyService implements SlugifyInterface
{
    private SlugifyInterface $slugify;

    public function __construct(?SlugifyInterface $slugify = null)
    {
        $this->slugify = $slugify ?: new Slugify();
    }

    /**
     * @param string $string
     * @param string|array|null $options
     * @return string
     */
    public function slugify($string, $options = null): string
    {
        return $this->slugify->slugify($string, $options);
    }

    /**
     * Zwraca ścieżkę do katalogu, w której poszczególne poziomy są slugiem (url friendly)
     *
     * @param string $path
     * @return string|null
     */
    public function slugifyPath(string $path): ?string
    {
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path));

        if (empty($parts)) {
            return null;
        }

        foreach ($parts as &$part) {
            $part = $this->slugify->slugify($part);
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }
}
