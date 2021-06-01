<?php

namespace Assistant\Module\Common\Extension;

interface SlugifyInterface
{
    public function slugify(string $string, ?array $options = null): ?string;

    public function slugifyPath(string $path): ?string;
}
