<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;
use SplFileInfo;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
abstract class AbstractReader
{
    /**
     * Obiekt klasy Slugify
     *
     * @var SlugifyInterface
     */
    protected SlugifyInterface $slugify;

    public function __construct(?SlugifyInterface $slugify = null)
    {
        $this->slugify = $slugify ?: new Slugify();
    }

    /**
     * Przetwarza katalog znajdujący się w kolekcji
     *
     * @param SplFileInfo $node
     */
    abstract public function read(SplFileInfo $node);

    /**
     * Zwraca ścieżkę do katalogu, w której poszczególne poziomy są slugiem
     *
     * @param string $path
     * @return string
     */
    protected function slugifyPath($path)
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
