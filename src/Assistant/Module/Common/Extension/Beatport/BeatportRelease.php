<?php

namespace Assistant\Module\Common\Extension\Beatport;

final class BeatportRelease
{
    private const DOMAIN = 'https://www.beatport.com';

    private int $id;
    private string $url;
    private string $name;

    public function __construct(int $id, string $type, string $slug, string $name)
    {
        $this->id = $id;
        $this->url = sprintf('%s/%s/%s/%d', self::DOMAIN, $type, $slug, $id);
        $this->name = $name;
    }

    public static function create($release): BeatportRelease
    {
        $beatportRelease = new BeatportRelease(
            $release['id'],
            $release['type'],
            $release['slug'],
            $release['name'],
        );

        return $beatportRelease;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'name' => $this->name,
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

    public function getName(): string
    {
        return $this->name;
    }
}