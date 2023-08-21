<?php

namespace Assistant\Module\Common\Extension\Beatport;

final readonly class BeatportTrack
{
    private const DEFAULT_MIX_NAME = 'Original Mix';

    private function __construct(
        public int $id,
        public string $url,
        public array $artists,
        public string $title,
        public string $mixName,
        public ?array $remixers,
        public string $name,
        public BeatportRelease $release,
        public ?int $trackNumber,
        public string $genre,
        public ?string $subGenre,
        /** @var BeatportChart[]|null */
        public ?array $charts,
        public int $length,
        public int $bpm,
        public string $key,
    ) {
    }

    public static function create(array $track): self
    {
        $url = sprintf('%s/%s/%s/%d', Beatport::DOMAIN, Beatport::TYPE_TRACKS, $track['slug'], $track['id']);
        $artists = array_map(static fn ($artist) => $artist['name'], $track['artists']);
        $remixers = array_map(static fn ($remixer) => $remixer['name'], $track['remixers']);
        $mixName = $track['mix_name'] ?? self::DEFAULT_MIX_NAME;
        $name = sprintf('%s - %s (%s)', implode(', ', $artists), $track['name'], $mixName);
        $charts = array_map(static fn ($chart) => BeatportChart::create($chart), $track['charts'] ?? []);

        $beatportTrack = new self(
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
            length: $track['length_ms'],
            bpm: $track['bpm'],
            key: $track['key']['name'],
        );

        return $beatportTrack;
    }
}
