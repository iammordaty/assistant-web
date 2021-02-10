<?php

namespace Assistant\Module\Common\Extension\TrackSearch;

use Curl\Curl;

/**
 * Ogólna klasa wyszukująca
 */
final class GoogleSearchApiClient
{
    private const API_BASE_URL = 'https://www.googleapis.com/customsearch/v1';

    private string $apiKey;

    /**
     * @link https://cse.google.com/all
     *
     * @var string
     */
    private string $apiSearchId;

    public function __construct(string $apiKey, string $apiSearchId)
    {
        $this->apiKey = $apiKey;
        $this->apiSearchId = $apiSearchId;
    }

    public function search(string $query): array
    {
        $url = sprintf(
            '%s?key=%s&cx=%s&q=%s',
            self::API_BASE_URL,
            $this->apiKey,
            $this->apiSearchId,
            urlencode($query)
        );

        // TODO: użyć guzzle, przenieść do konstruktora
        $curl = new Curl();
        $curl->setTimeout(3 * 60);

        $response = $curl->get($url);

        if (empty($response->items)) {
            return [];
        }

        return $response->items;
    }
}
