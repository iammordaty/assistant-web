<?php

namespace Assistant\Module\Common\Extension\Pagerfanta;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Pagerfanta\RouteGenerator\RouteGeneratorInterface;

final class RouteGenerator implements RouteGeneratorInterface
{
    public function __construct(
        private readonly RouteResolver $routeResolver,
        private readonly ?Route $route
    ) {
    }

    public function __invoke(int $page): string
    {
        $baseUrl = $this->route ? $this->routeResolver->resolve($this->route) : '';

        if ($page === 1) {
            return $baseUrl;
        }

        $urlWithPage = $this->route->getQuery()
            ? sprintf('%s&page=%d', $baseUrl, $page)
            : sprintf('?page=%d', $page);

        return $urlWithPage;
    }
}
