<?php

namespace Assistant\Module\Common\Extension;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use League\Flysystem\Adapter\Local;

final class BeatportApiClient implements BeatportApiClientInterface
{
    private function __construct(private ClientInterface $client)
    {
    }

    public static function create(Config $config): self
    {
        $stack = HandlerStack::create();

        $cacheMiddleware = new CacheMiddleware(
            new GreedyCacheStrategy(
                new FlysystemStorage(new Local($config->get('base_dir') . '/var/cache')),
                172800, // 48h, the TTL in seconds
            )
        );
        $stack->push($cacheMiddleware);

        $beatportApiConfig = $config->get(self::class);

        $authMiddleware = BeatportAuthMiddleware::factory($beatportApiConfig, $config->get('base_dir') . '/var/');
        $stack->push($authMiddleware);

        $client = new Client([
            'base_uri' => $beatportApiConfig['api_url'],
            'handler' => $stack,
        ]);

        return new self($client);
    }

    public function search(array $query): array
    {
        $response = $this->client->get('v4/catalog/search', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }

    public function track(int $trackId): array
    {
        $url = 'v4/catalog/tracks/' . $trackId;
        $response = $this->client->get($url);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }

    public function releases(array $query): array
    {
        $response = $this->client->get('v4/catalog/releases', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }

    public function charts(array $query): array
    {
        $response = $this->client->get('v4/catalog/charts', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }
}
