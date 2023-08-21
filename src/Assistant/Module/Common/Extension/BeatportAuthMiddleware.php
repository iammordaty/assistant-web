<?php

namespace Assistant\Module\Common\Extension;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use DateTime;
use GuzzleHttp\Client;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Psr\Http\Message\RequestInterface;

/** @idea Tę klasę najlepiej byłoby wydzielić z projektu i podzielić na wzór iammordaty/beatport-oauth-middleware */
final readonly class BeatportAuthMiddleware
{
    private const CACHE_KEY = 'beatport_api_access_token';

    private function __construct(
        private string $apiUrl,
        private string $clientId,
        private string $clientSecret,
        private string $username,
        private string $password,
        private string $cacheDir,
    ) {
    }

    public static function factory(array $config, string $cacheDir): self
    {
        return new self(
            $config['api_url'],
            $config['client_id'],
            $config['client_secret'],
            $config['username'],
            $config['password'],
            $cacheDir
        );
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            [ 'token_type' => $tokenType, 'access_token' => $accessToken ] = $x = $this->getAccessToken();

            $request = $request->withHeader('Authorization', sprintf('%s %s', $tokenType, $accessToken));

            return $handler($request, $options);
        };
    }

    private function getAccessToken(): array
    {
        $cache = new FilesystemCachePool(new Filesystem(new Local($this->cacheDir)));

        if ($cache->has(self::CACHE_KEY)) {
            return $cache->get(self::CACHE_KEY);
        }

        $accessToken = $this->fetchAccessToken();

        $expires = (new DateTime())->modify(sprintf('+%s seconds', $accessToken['expires_in']));

        $cache->set(
            key: self::CACHE_KEY,
            value: $accessToken,
            ttl: (new DateTime())->diff($expires)
        );

        return $accessToken;
    }

    private function fetchAccessToken(): array
    {
        $client = new Client([ 'base_uri' => $this->apiUrl ]);

        $response = $client->post('v4/auth/o/token/', [
            'form_params' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
}
