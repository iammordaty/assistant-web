<?php

namespace Assistant\Module\Common\Extension\Pagerfanta;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Pagerfanta\RouteGenerator\RouteGeneratorInterface;

final readonly class RouteGenerator implements RouteGeneratorInterface
{
    public function __construct(
        private RouteResolver $routeResolver,
        private ?Route $route,
    ) {
    }

    public function __invoke(int $page): string
    {
        $baseUrl = '';
        $route = $this->route;

        if ($route) {
            $query = $route->getQuery();

            if (isset($query['page'])) {
                unset($query['page']);

                $route = $route->withQuery($query);
            }

            $baseUrl = $this->routeResolver->resolve($route);
        }

        if ($page === 1) {
            return $baseUrl;
        }

        $urlWithPage = $route->getQuery()
            ? sprintf('%s&page=%d', $baseUrl, $page)
            : sprintf('?page=%d', $page);

        return $urlWithPage;
    }
}
