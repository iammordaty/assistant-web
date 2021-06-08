<?php

namespace Assistant\Module\Common\Extension\Beatport;

final class BeatportRelease
{
    public function __construct(
        private string $url,
        private string $name,
        private string $label,
    ) {
    }

    public static function create($release): BeatportRelease
    {
        $url = sprintf('%s/%s/%s/%d', Beatport::DOMAIN, Beatport::TYPE_RELEASE, $release['slug'], $release['id']);

        $beatportRelease = new BeatportRelease(
            url: $url,
            name: $release['name'],
            label: $release['label']['name'],
        );

        return $beatportRelease;
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'name' => $this->name,
            'label' => $this->label,
        ];
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
