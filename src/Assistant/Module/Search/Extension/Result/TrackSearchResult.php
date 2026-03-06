<?php

namespace Assistant\Module\Search\Extension\Result;

use Assistant\Module\Track\Model\Track;

final readonly class TrackSearchResult
{
    /** @param Track[] $tracks */
    public function __construct(
        public iterable $tracks,
        public int $total,
        public int $page,
        public ?int $limit = null,
    ) {
    }

    public function hasTracks(): bool
    {
        return $this->total >= 1;
    }
}
