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
        // ręczne trimowanie nazwy katalogu oraz przekazanie trim = false jest obejściem
        // tego że dla katalogów "/collection/other/2006" oraz "collection/other/- 2006"
        // jest generowany ten sam slug, tj: "collection/other/2006";
        // zastanowić się, czy można to obejść bardziej elegancko, systemowo i bez utraty na wydajności indeksowania
        // (guid to także parent dla elementów potomnych)

        $dirs = array_map(
            fn($dir) => $this->slugify->slugify(trim($dir), [ 'trim' => false ]),
            array_filter(explode(DIRECTORY_SEPARATOR, $path))
        );

        return implode(DIRECTORY_SEPARATOR, $dirs) ?: null;
    }
}
