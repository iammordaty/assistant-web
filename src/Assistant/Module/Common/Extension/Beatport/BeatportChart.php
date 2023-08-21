<?php

namespace Assistant\Module\Common\Extension\Beatport;

final readonly class BeatportChart
{
    private function __construct(
        public string $url,
        public ?string $artist,
        /**
         * Określa, że lista została stworzona przez DJ-a, producenta, lub zespół zarejestrowany na Beatport
         * (np. Voorn-a), a nie "zwykłego" użytkownika portalu
         */
        public bool $isOfficial,
        public string $name,
        public array $genres,
    ) {
    }

    public static function create(array $chart): self
    {
        $url = sprintf('%s/%s/%s/%d', Beatport::DOMAIN, Beatport::TYPE_CHARTS, $chart['slug'], $chart['id']);
        $genres = array_map(static fn ($genre) => $genre['name'], $chart['genres']);

        $beatportChart = new self(
            url: $url,
            artist: $chart['artist']['name'] ?? null,
            isOfficial: isset($chart['artist']),
            name: $chart['name'],
            genres: $genres,
        );

        return $beatportChart;
    }
}
