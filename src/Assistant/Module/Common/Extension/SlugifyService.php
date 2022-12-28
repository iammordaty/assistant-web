<?php

namespace Assistant\Module\Common\Extension;

use Cocur\Slugify\Slugify;

final class SlugifyService implements SlugifyInterface
{
    public function __construct(private Slugify $slugify = new Slugify())
    {
    }

    public function slugify(string $string, ?array $options = null): ?string
    {
        return $this->slugify->slugify($string, $options);
    }

    /** Zwraca ścieżkę do katalogu, w której poszczególne poziomy są slugiem (url friendly) */
    public function slugifyPath(string $path): ?string
    {
        // ręczne trim-owanie nazwy katalogu oraz przekazanie trim = false jest obejściem
        // tego, że dla katalogów "/collection/other/2006" oraz "collection/other/- 2006"
        // jest generowany ten sam slug, tj: "collection/other/2006";
        // zastanowić się, czy można to obejść bardziej elegancko, systemowo i bez utraty na wydajności indeksowania
        // (guid to także parent dla elementów potomnych)
        // jednocześnie rtrim zapobiega dodawaniu "-" na końcu utworów z remiksem w nawiasach w ścieżkach, np.:
        // "/.../Just Like You (Joris Voorn Remix)" -> "/.../just-like-you-joris-voorn-remix-"

        $dirs = array_map(
            function (string $dir): string {
                $dir = trim($dir);
                $dir = $this->slugify->slugify($dir, [ 'trim' => false ]);
                $dir = rtrim($dir, '-');

                return $dir;
            },
            array_merge(array_filter(explode(DIRECTORY_SEPARATOR, $path)))
        );

        return implode(DIRECTORY_SEPARATOR, $dirs) ?: null;
    }
}
