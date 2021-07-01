<?php

namespace Assistant\Module\Collection\Extension\Autocomplete;

final class TrackAutocompleteEntry
{
    public function __construct(
        private string $guid,
        private string $name,
        private string $url,
    ) {
    }

    public function toArray(): array
    {
        return [
            'guid' => $this->guid,
            'name' => $this->name,
            'url' => $this->url,
        ];
    }
}
