<?php

namespace Assistant\Module\Common\Extension;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class Redirect
{
    public static function create(
        ServerRequestInterface $request,
        string $routeName,
        ?array $data = [],
        ?array $queryParams = [],
        ?int $status = 302,
    ): ResponseInterface {
        $redirectUrl = UrlFactory::fromRequest($request)
            ->setRouteName($routeName)
            ->setData($data)
            ->setQueryParams($queryParams)
            ->getUrl();

        $redirect = (new Response())
            ->withHeader('Location', $redirectUrl)
            ->withStatus($status);

        return $redirect;
    }
}
