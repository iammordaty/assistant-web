<?php

namespace Assistant\Module\Common\Extension;

use BeatportOauth\AccessTokenProvider;
use BeatportOauth\OauthMiddlewareFactory;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

final class BeatportApiClient
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public static function create(array $oauthParams, string $baseDir): BeatportApiClient
    {
        $filesystemAdapter = new Local($baseDir);
        $filesystem = new Filesystem($filesystemAdapter);

        $cachePool = new FilesystemCachePool($filesystem);
        $cacheConfig = [ 'key' => 'beatport_access_token_info' ];

        $cache = new SimpleCacheBridge($cachePool);

        $beatportOauthMiddleware = OauthMiddlewareFactory::createWithCachedToken(
            $oauthParams,
            $cache,
            $cacheConfig
        );

        $cacheMiddleware = new CacheMiddleware(
            new GreedyCacheStrategy(
                new FlysystemStorage(new Local($baseDir . '/cache')),
                3600, // 1h, the TTL in seconds
            )
        );

        $stack = HandlerStack::create();

        $stack->push($beatportOauthMiddleware);
        $stack->push($cacheMiddleware);

        $client = new Client([
            'auth' => 'oauth',
            'base_uri' => AccessTokenProvider::BASE_URI,
            'handler' => $stack,
        ]);

        return new self($client);
    }

    public function search($query): array
    {
        $response = $this->client->get('/catalog/3/search', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }

    public function tracks($query): array
    {
        $response = $this->client->get('catalog/3/tracks', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }

    public function charts($query): array
    {
        $response = $this->client->get('catalog/3/charts', [
            'query' => $query,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        return $contents;
    }
}
