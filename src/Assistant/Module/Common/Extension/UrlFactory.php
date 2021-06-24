<?php

namespace Assistant\Module\Common\Extension;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;

final class UrlFactory
{
    private string $routeName;
    private array $data = [];
    private array $queryParams = [];

    public function __construct(private RouteParserInterface $routeParser)
    {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        return new self($routeParser);
    }

    public function setRouteName(string $routeName): self
    {
        $this->routeName = $routeName;

        return $this;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function setQueryParams(array $queryParams): self
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    public function getUrl(): string
    {
        if (!$this->routeName) {
            throw new \BadMethodCallException('Cannot create route without its name.');
        }

        $redirectUrl = $this->routeParser->urlFor($this->routeName, $this->data, $this->queryParams);

        return $redirectUrl;
    }
}
