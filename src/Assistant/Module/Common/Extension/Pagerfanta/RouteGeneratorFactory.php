<?php

namespace Assistant\Module\Common\Extension\Pagerfanta;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Pagerfanta\RouteGenerator\RouteGeneratorFactoryInterface;

final readonly class RouteGeneratorFactory implements RouteGeneratorFactoryInterface
{
    public function __construct(private RouteResolver $routeResolver)
    {
    }

    public function create(array $options = []): RouteGenerator
    {
        $route = null;
        $routeOpts = $options['route'] ?? null;

        if ($routeOpts) {
            $route = Route::create(
                $routeOpts['name'],
                $routeOpts['params'] ?? [],
                $routeOpts['query'] ?? [],
            );
        }

        return new RouteGenerator($this->routeResolver, $route);
    }
}
