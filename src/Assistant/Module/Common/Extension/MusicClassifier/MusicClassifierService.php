<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use Assistant\Module\Common\Extension\Config;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use SplFileInfo;

final class MusicClassifierService
{
    /** Analizatory uruchamiane dla każdego utworu */
    private const array ANALYZERS = [ 'bpm', 'key', 'genre', 'mood', 'tags', 'instrument' ];

    private ClientInterface $client;

    public function __construct(Config $config)
    {
        $this->client = new Client([
            'base_uri' => $config->get('music_classifier.base_url'),
            'timeout' => 0,
        ]);
    }

    public function analyze(SplFileInfo $track): MusicClassifierResult
    {
        try {
            $response = $this->client->request('GET', '/process', [
                'query' => [
                    'pathname' => $track->getPathname(),
                    'analyzers' => implode(',', self::ANALYZERS),
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new MusicClassifierRequestException($track, $e);
        }

        $rawResult = json_decode($response->getBody()->getContents(), true);

        if (!is_array($rawResult)) {
            throw new MusicClassifierResultIncompleteException([]);
        }

        return MusicClassifierResult::fromApiResponse($rawResult);
    }
}
