<?php

namespace Assistant\Module\Common\Extension;

use Slim\Interfaces\RouteParserInterface;

final class RouteResolver
{
    public function __construct(private RouteParserInterface $routeParser)
    {
    }

    public function resolve(Route $route): string
    {
        $url = $this->routeParser->urlFor($route->getName(), $route->getParams(), $route->getQuery());

        return $url;
    }
}
