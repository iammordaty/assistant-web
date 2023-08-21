<?php

namespace Assistant\Module\Common\Extension\TrackSearch;

final class EmptySearchTracks
{
    public function __invoke(string $query): array
    {
        return [];
    }
}
