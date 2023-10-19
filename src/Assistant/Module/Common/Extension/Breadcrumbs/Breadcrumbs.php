<?php

namespace Assistant\Module\Common\Extension\Breadcrumbs;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\SlugifyService;

final readonly class Breadcrumbs
{
    public function __construct(private SlugifyService $slugify)
    {
    }

    /**
     * Zwraca ścieżkę katalogu nadrzędnego w postaci breadcrumbs-ów
     *
     * @param string $path
     * @param callable $routeGenerator
     * @return Breadcrumb[]
     */
    public function create(string $path, callable $routeGenerator): array
    {
        $path = ltrim($path, DIRECTORY_SEPARATOR);

        if (!$path) {
            return [];
        }

        $parts = explode(DIRECTORY_SEPARATOR, $path);

        $result = [];
        $guids = [];
        $dirs = [];

        foreach ($parts as $name) {
            $guids[] = $this->slugify->slugify($name);
            $dirs[] = $name;

            $breadcrumb = new Breadcrumb(
                routeOrRouteGenerator: fn (Breadcrumb $breadcrumb): ?Route => $routeGenerator($breadcrumb),
                name: $name,
                guid: implode(DIRECTORY_SEPARATOR, $guids),
                pathname: DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $dirs),
            );

            $result[] = $breadcrumb;
        }

        return $result;
    }
}
