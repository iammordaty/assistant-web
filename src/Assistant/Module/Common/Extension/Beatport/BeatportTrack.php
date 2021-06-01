<?php

namespace Assistant\Module\Common\Extension\Beatport;

final class BeatportTrack
{
    private const DOMAIN = 'https://www.beatport.com';

    private const DEFAULT_MIX_NAME = 'Original Mix';

    private const ARTIST_TYPE_ARTIST = 'artist';
    private const ARTIST_TYPE_REMIXER = 'remixer';

    private int $id;
    private string $url;
    private array $artists;
    private string $title;
    private string $name;
    private string $mixName;
    private ?array $remixers;
    private BeatportRelease $release;
    private int $trackNumber;
    private array $genres;
    private ?array $subGenres;
    /** @var BeatportChart[]|null */
    private ?array $charts;
    private \DateTime $releaseDate;
    private string $label;
    private int $length;
    private int $bpm;
    private string $key;

    public function __construct(
        int $id,
        string $type,
        string $slug,
        array $rawArtists,
        string $rawTitle,
        string $name,
        string $mixName,
        array $release,
        int $trackNumber,
        array $genres,
        ?array $subGenres,
        ?array $rawCharts,
        \DateTime $releaseDate,
        string $label,
        int $length,
        int $bpm,
        string $key
    ) {

        $artists = array_filter($rawArtists, static fn($performer) => (
            $performer['type'] === self::ARTIST_TYPE_ARTIST)
        );

        $remixers = array_filter($rawArtists, static fn($performer) => (
            $performer['type'] === self::ARTIST_TYPE_REMIXER)
        );

        $charts = array_map(static fn($chart) => BeatportChart::create($chart), $rawCharts);

        $this->id = $id;
        $this->url = sprintf('%s/%s/%s/%d', self::DOMAIN, $type, $slug, $id);
        $this->artists = array_merge(array_map(static fn($artist) => $artist['name'], $artists));
        $this->title = $rawTitle;
        $this->name = $name;
        $this->mixName = $mixName ?: self::DEFAULT_MIX_NAME;
        $this->remixers = array_merge(array_map(static fn($remixer) => $remixer['name'], $remixers)) ?: null;
        $this->release = BeatportRelease::create($release);
        $this->trackNumber = $trackNumber;
        $this->genres = array_map(static fn($genre) => $genre['name'], $genres);
        $this->subGenres = array_map(static fn($subGenre) => $subGenre['name'], $subGenres) ?: null;
        $this->charts = $charts ?: null;
        $this->releaseDate = $releaseDate;
        $this->label = $label;
        $this->length = $length;
        $this->bpm = $bpm;
        $this->key = $key;
    }

    public static function create($rawTrack): BeatportTrack
    {
        $beatportTrack = new BeatportTrack(
            $rawTrack['id'],
            $rawTrack['type'],
            $rawTrack['slug'],
            $rawTrack['artists'],
            $rawTrack['title'],
            $rawTrack['name'],
            $rawTrack['mixName'],
            $rawTrack['release'],
            $rawTrack['trackNumber'],
            $rawTrack['genres'],
            $rawTrack['subGenres'],
            $rawTrack['charts'],
            new \DateTime($rawTrack['releaseDate']),
            $rawTrack['label']['name'],
            $rawTrack['lengthMs'],
            $rawTrack['bpm'],
            html_entity_decode($rawTrack['key']['shortName']),
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
            'name' => $this->name,
            'mixName' => $this->mixName,
            'remixers' => $this->remixers,
            'release' => $this->release->toArray(),
            'trackNumber' => $this->trackNumber,
            'genres' => $this->genres,
            'subGenres' => $this->subGenres,
            'charts' => $this->charts, // map -> toArray()
            'releaseDate' => $this->releaseDate->format(\DateTimeInterface::ATOM),
            'label' => $this->label,
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getRemixers(): ?array
    {
        return $this->remixers;
    }

    public function getRelease(): BeatportRelease
    {
        return $this->release;
    }

    public function getTrackNumber(): int
    {
        return $this->trackNumber;
    }

    public function getGenres(): array
    {
        return $this->genres;
    }

    public function getSubGenres(): ?array
    {
        return $this->subGenres;
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

    public function getLabel(): string
    {
        return $this->label;
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
