<?php

namespace Assistant\Module\Common\Extension;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use League\Flysystem\Adapter\Local;
use Psr\Http\Message\RequestInterface;

/**
 * To jest tymczasowa wersja klasy, używana do czasu uzyskania dostępu do https://api.beatport.com/v4/
 */
final class BeatportApiClient implements BeatportApiClientInterface
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public static function create(string $authorization, string $baseDir): self
    {
        $stack = HandlerStack::create();

        $cacheMiddleware = new CacheMiddleware(
            new GreedyCacheStrategy(
                new FlysystemStorage(new Local($baseDir . '/var/cache')),
                3600, // 1h, the TTL in seconds
            )
        );

        $stack->push($cacheMiddleware);

        $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($authorization) {
            return $request->withHeader('Authorization', $authorization);
        }));

        $client = new Client([
            'base_uri' => 'https://api.beatport.com/v4/',
            'handler' => $stack,
        ]);

        return new self($client);
    }

    public function search(array $query): array
    {
        $response = $this->client->get('/v4/catalog/search', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }

    public function track(int $trackId): array
    {
        $url = '/v4/catalog/tracks/' . $trackId;
        $response = $this->client->get($url);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }

    public function charts(array $query): array
    {
        $response = $this->client->get('/v4/catalog/charts', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }
}
