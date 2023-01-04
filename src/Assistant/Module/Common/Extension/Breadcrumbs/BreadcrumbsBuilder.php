<?php

namespace Assistant\Module\Common\Extension\Breadcrumbs;

final readonly class BreadcrumbsBuilder
{
    private string $path;

    private mixed $routeGenerator;

    public function __construct(private Breadcrumbs $breadcrumbs)
    {
    }

    public function withPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function withRouteGenerator(callable $routeGenerator): self
    {
        $this->routeGenerator = $routeGenerator;

        return $this;
    }

    /** @return Breadcrumb[] */
    public function createBreadcrumbs(): array
    {
        if (!$this->path) {
            throw new \RuntimeException(sprintf('Path must be set before calling the "%s" method', __METHOD__));
        }

        if (!$this->routeGenerator) {
            throw new \RuntimeException(
                sprintf('Route generator must be set before calling the "%s" method', __METHOD__)
            );
        }

        return $this->breadcrumbs->create($this->path, $this->routeGenerator);
    }
}
