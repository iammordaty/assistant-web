<?php

namespace Assistant\Module\Common\Extension\Beatport;

final class BeatportTrack
{
    private const DEFAULT_MIX_NAME = 'Original Mix';

    public function __construct(
        private int $id,
        private string $url,
        private array $artists,
        private string $title,
        private string $mixName,
        private ?array $remixers,
        private string $name,
        private BeatportRelease $release,
        private ?int $trackNumber,
        private string $genre,
        private ?string $subGenre,
        /** @var BeatportChart[]|null */
        private ?array $charts,
        private \DateTime $releaseDate,
        private int $length,
        private int $bpm,
        private string $key,
    ) {
    }

    public static function create($track): BeatportTrack
    {
        $url = sprintf('%s/%s/%s/%d', Beatport::DOMAIN, Beatport::TYPE_TRACKS, $track['slug'], $track['id']);
        $artists = array_map(static fn($artist) => $artist['name'], $track['artists']);
        $remixers = array_map(static fn($remixer) => $remixer['name'], $track['remixers']);
        $mixName = $track['mix_name'] ?? self::DEFAULT_MIX_NAME;
        $name = sprintf('%s - %s (%s)', implode(',', $artists), $track['name'], $mixName);
        $charts = array_map(static fn($chart) => BeatportChart::create($chart), $track['charts']);

        /** @noinspection PhpUnhandledExceptionInspection */
        $releaseDate = new \DateTime($track['new_release_date']);

        $beatportTrack = new BeatportTrack(
            id: $track['id'],
            url: $url,
            artists: $artists,
            title: $track['name'],
            mixName: $mixName,
            remixers: $remixers,
            name: $name,
            release: BeatportRelease::create($track['release']),
            trackNumber: $track['number'],
            genre: $track['genre']['name'],
            subGenre: $track['sub_genre']['name'] ?? null,
            charts: $charts ?: null,
            releaseDate: $releaseDate,
            length: $track['length_ms'],
            bpm: $track['bpm'],
            key: $track['key']['name'],
        );

        return $beatportTrack;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'artists' => $this->artists,
            'title' => $this->title,
            'mixName' => $this->mixName,
            'remixers' => $this->remixers,
            'name' => $this->name,
            'release' => $this->release->toArray(),
            'trackNumber' => $this->trackNumber,
            'genres' => $this->genre,
            'subGenres' => $this->subGenre,
            'charts' => $this->charts,
            'releaseDate' => $this->releaseDate->format(\DateTimeInterface::ATOM),
            'length' => $this->length,
            'bpm' => $this->bpm,
            'key' => $this->key,
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getArtists(): array
    {
        return $this->artists;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMixName(): string
    {
        return $this->mixName;
    }

    public function getRemixers(): ?array
    {
        return $this->remixers;
    }

    /**
     * Zwraca nazwę utworu w pełnej postaci, tj. Wykonawca - Tytuł utworu (Nazwa remiksu)
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getRelease(): BeatportRelease
    {
        return $this->release;
    }

    public function getTrackNumber(): ?int
    {
        return $this->trackNumber;
    }

    public function getGenre(): string
    {
        return $this->genre;
    }

    public function getSubGenre(): ?string
    {
        return $this->subGenre;
    }

    /**
     * @return BeatportChart[]|null
     */
    public function getCharts(): ?array
    {
        return $this->charts;
    }

    public function getReleaseDate(): \DateTime
    {
        return $this->releaseDate;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getBpm(): int
    {
        return $this->bpm;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
