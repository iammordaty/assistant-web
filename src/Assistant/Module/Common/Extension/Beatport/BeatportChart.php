<?php

namespace Assistant\Module\Common\Extension\Beatport;

final class BeatportChart
{
    public function __construct(
        private string $url,
        private ?string $artist,
        private bool $isOfficial,
        private string $name,
        private array $genres,
    ) {
    }

    public static function create($chart): BeatportChart
    {
        $url = sprintf('%s/%s/%s/%d', Beatport::DOMAIN, Beatport::TYPE_CHARTS, $chart['slug'], $chart['id']);
        $genres = array_map(static fn($genre) => $genre['name'], $chart['genres']);

        $beatportChart = new BeatportChart(
            url: $url,
            artist: $chart['artist']['name'] ?? null,
            isOfficial: isset($chart['artist']),
            name: $chart['name'],
            genres: $genres,
        );

        return $beatportChart;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    /**
     * Określa że lista została stworzona przez DJ-a / producenta / zespół zarejestrowany na beatport (np. Voorna),
     * a nie "zwykłego" użytkownika portalu
     *
     * @return bool
     */
    public function isOfficial(): bool
    {
        return $this->isOfficial;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGenres(): array
    {
        return $this->genres;
    }
}
