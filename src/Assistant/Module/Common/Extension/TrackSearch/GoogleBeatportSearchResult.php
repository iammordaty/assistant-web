<?php

namespace Assistant\Module\Common\Extension\TrackSearch;

final class GoogleBeatportSearchResult
{
    private int $id;
    private string $url;

    public function __construct(int $id, string $url)
    {
        $this->id = $id;
        $this->url = $url;
    }

    public static function factory(\stdClass $result): GoogleBeatportSearchResult
    {
        $path = parse_url($result->link, PHP_URL_PATH);

        // https://www.beatport.com/{type}/{slug}/{id}
        [ , , $id ] = explode('/', trim($path, '/'));

        return new self((int) $id, $result->link);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
