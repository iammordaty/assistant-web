<?php

namespace Assistant\Module\Common\Extension\Beatport;

final class BeatportChart
{
    private const DOMAIN = 'https://www.beatport.com';

    private int $id;
    private string $url;
    private ?string $artist;
    private string $name;
    private array $genres;
    private ?array $subGenres;

    public function __construct(
        int $id,
        string $type,
        string $slug,
        ?array $rawArtist,
        string $name,
        array $genres,
        ?array $subGenres
    ) {
        $this->id = $id;
        $this->url = sprintf('%s/%s/%s/%d', self::DOMAIN, $type, $slug, $id);
        $this->artist = $rawArtist ? $rawArtist['name'] : null;
        $this->name = $name;
        $this->genres = array_map(static fn($genre) => $genre['name'], $genres);
        $this->subGenres = array_unique(array_map(static fn($subGenre) => $subGenre['name'], $subGenres)) ?: null;
    }

    public static function create($chart): BeatportChart
    {
        $beatportChart = new BeatportChart(
            $chart['id'],
            $chart['type'],
            $chart['slug'],
            $chart['chartOwner'],
            $chart['name'],
            $chart['genres'],
            $chart['subGenres'],
        );

        return $beatportChart;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGenres(): array
    {
        return $this->genres;
    }

    public function getSubGenres(): ?array
    {
        return $this->subGenres;
    }
}