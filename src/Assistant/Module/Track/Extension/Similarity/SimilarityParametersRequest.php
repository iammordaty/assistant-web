<?php

namespace Assistant\Module\Track\Extension\Similarity;

final class SimilarityParametersRequest
{
    public function __construct(
        public readonly ?array $providers = null,
        public readonly ?float $bpm = null,
        public readonly ?string $genre = null,
        public readonly ?string $musicalKey = null,
        public readonly ?int $year = null,
    ) {
    }
}
