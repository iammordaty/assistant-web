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

    public static function create(string $baseUri, string $authorization, string $baseDir): self
    {
        $stack = HandlerStack::create();

        $cacheMiddleware = new CacheMiddleware(
            new GreedyCacheStrategy(
                new FlysystemStorage(new Local($baseDir . '/var/cache')),
                172800, // 48h, the TTL in seconds
            )
        );
        $stack->push($cacheMiddleware);

        $toJsonMiddleware = fn (RequestInterface $request): RequestInterface => (
            $request
                ->withHeader('Accept', 'application/json')
                ->withHeader('Authorization', $authorization)
        );
        $stack->push(Middleware::mapRequest($toJsonMiddleware));

        $client = new Client([
            'base_uri' => $baseUri,
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

    public function charts(array $query): array
    {
        $response = $this->client->get('v4/catalog/charts', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }
}
