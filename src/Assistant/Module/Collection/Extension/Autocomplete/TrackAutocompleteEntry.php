<?php

namespace Assistant\Module\Collection\Extension\Autocomplete;

final class TrackAutocompleteEntry
{
    public function __construct(
        public readonly string $guid,
        public readonly string $name,
        public readonly string $url,
    ) {
    }
}
