<?php

namespace Assistant\Module\Collection\Extension\Autocomplete;

final readonly class TrackAutocompleteEntry
{
    public function __construct(
        public string $guid,
        public array $artists,
        public string $title,
        public string $name,
        public string $genre,
        public string $length,
        public string $bpm,
        public string $initialKey,
        public string $url,
    ) {
    }
}
