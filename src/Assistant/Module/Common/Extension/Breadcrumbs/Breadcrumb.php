<?php

namespace Assistant\Module\Common\Extension\Breadcrumbs;

use Assistant\Module\Common\Extension\Route;
use Closure;

final readonly class Breadcrumb
{
    public Route $route;

    public function __construct(
        Closure|Route $routeOrRouteGenerator,
        public ?string $name = null,
        public ?string $guid = null,
        public ?string $pathname = null,
    ) {
        $route = $routeOrRouteGenerator;

        if (is_callable($route)) {
            $route = ($routeOrRouteGenerator)($this);
        }

        $this->route = $route;
    }
}
