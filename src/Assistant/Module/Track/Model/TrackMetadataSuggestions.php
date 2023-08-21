<?php

namespace Assistant\Module\Track\Model;

final readonly class TrackMetadataSuggestions
{
    public function __construct(
        public array $artist,
        public array $title,
        public array $album,
        public array $trackNumber,
        public array $year,
        public array $genre,
        public array $publisher,
        public array $bpm,
        public array $initialKey,
        public array $tags
    ) {
    }
}
