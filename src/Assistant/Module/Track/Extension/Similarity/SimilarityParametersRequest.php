<?php

namespace Assistant\Module\Track\Extension\Similarity;

final readonly class SimilarityParametersRequest
{
    public function __construct(
        public RelatedTracksMode $mode = RelatedTracksMode::SIMILAR,
        public ?array $providers = null,
        public ?float $bpm = null,
        public ?string $genre = null,
        public ?string $musicalKey = null,
        public ?int $year = null,
    ) {
    }
}
