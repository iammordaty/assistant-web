<?php

namespace Assistant\Module\Common\Extension\Beatport;

use DateTime;
use DateTimeInterface;

final readonly class BeatportRelease
{
    private function __construct(
        public string $url,
        public string $name,
        public string $label,
        public int $trackCount,
        public string $date,
    ) {
    }

    public static function create(array $release): self
    {
        $url = sprintf('%s/%s/%s/%d', Beatport::DOMAIN, Beatport::TYPE_RELEASE, $release['slug'], $release['id']);
        $releaseDate = (new DateTime($release['new_release_date']))->format(DateTimeInterface::ATOM);

        $beatportRelease = new self(
            url: $url,
            name: $release['name'],
            label: $release['label']['name'],
            trackCount: $release['track_count'],
            date: $releaseDate,
        );

        return $beatportRelease;
    }
}
