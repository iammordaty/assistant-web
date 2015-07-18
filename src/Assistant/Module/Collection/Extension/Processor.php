<?php

namespace Assistant\Module\Collection\Extension;

use Cocur\Slugify\Slugify;

/**
 * Klasa, której zadaniem jest przetwarzanie katalogów znajdujących się w kolekcji
 */
abstract class Processor
{
    /**
     * @var array
     */
    private $parameters;

    /**
     * Obiekt klasy Slugify
     *
     * @var Slugify
     */
    protected $slugify;

    /**
     * Konstruktor
     *
     * @param array|null $parameters
     */
    public function __construct(array $parameters = null)
    {
        if ($parameters !== null) {
            $this->parameters = $parameters;
        }

        $this->slugify = new Slugify();
    }

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
