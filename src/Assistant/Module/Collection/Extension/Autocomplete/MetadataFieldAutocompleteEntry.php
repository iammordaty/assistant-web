<?php

namespace Assistant\Module\Collection\Extension\Autocomplete;

final readonly class MetadataFieldAutocompleteEntry
{
    public function __construct(
        public string $type,
        public string $name,
    ) {
    }
}
