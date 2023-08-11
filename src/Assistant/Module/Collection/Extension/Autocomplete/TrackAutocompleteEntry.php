<?php

namespace Assistant\Module\Collection\Extension\Autocomplete;

final readonly class TrackAutocompleteEntry
{
    public function __construct(
        public string $guid,
        public string $name,
        public string $url,
    ) {
    }
}
