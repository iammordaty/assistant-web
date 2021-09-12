<?php

namespace Assistant\Module\Common\Extension\TrackSearch;

use GuzzleHttp\Client;

/**
 * @link https://developers.google.com/custom-search/v1
 * @link https://programmablesearchengine.google.com/cse/all
 */
final class GoogleSearchApiClient
{
    private const API_HOSTNAME = 'https://www.googleapis.com';
    private const API_SEARCH_ENDPOINT = '/customsearch/v1';

    private string $apiKey;
    private string $apiSearchId;

    private Client $client;

    public function __construct(string $apiKey, string $apiSearchId)
    {
        $this->apiKey = $apiKey;
        $this->apiSearchId = $apiSearchId;

        $this->client = new Client([
            'base_uri' => self::API_HOSTNAME,
            'timeout' => 60 /* sekund */
        ]);
    }

    public function search(string $query): array
    {
        $queryParams = [
            'key' => $this->apiKey,
            'cx' => $this->apiSearchId,
            'q' => $query,
        ];

        $url = sprintf('%s?%s', self::API_SEARCH_ENDPOINT, http_build_query($queryParams));

        $response = $this->client->get($url);
        $body = json_decode((string) $response->getBody());

        return $body->items ?? [];
    }
}
