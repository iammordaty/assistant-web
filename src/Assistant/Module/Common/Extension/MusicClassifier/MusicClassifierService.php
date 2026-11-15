<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use Assistant\Module\Common\Extension\Config;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use SplFileInfo;

final readonly class MusicClassifierService
{
    public function __construct(private ClientInterface $client)
    {
    }

    public static function create(Config $config): self
    {
        $musicClassifierConfig = $config->get(self::class);

        $client = new Client([
            'base_uri' => $musicClassifierConfig['api_url'],
            'timeout' => 2,
        ]);

        return new self($client);
    }

    public function analyze(SplFileInfo $track): MusicClassifierResult
    {
        try {
            $response = $this->client->request('GET', 'process', [
                'query' => [ 'pathname' => $track->getPathname() ],
            ]);

            $rawResult = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException|JsonException $e) {
            throw new MusicClassifierException($e->getMessage(), previous: $e);
        }

        $result = MusicClassifierResult::fromResponse($rawResult);

        return $result;
    }
}
