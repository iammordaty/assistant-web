<?php

namespace Assistant\Module\Common\Extension\Breadcrumbs;

/**
 * @idea: Może ta klasa powinna nazywać się np. DirectoryTreeBuilder albo PathTreeBuilder, bo
 *        nie zawsze wykorzystywana jest w kontekście budowania breadcrumbs-ów sensu stricte
 */
final readonly class BreadcrumbsBuilder
{
    private string $path;

    private mixed $routeGenerator;

    public function __construct(private Breadcrumbs $breadcrumbs)
    {
    }

    public function withPath(string $path): self
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    public function withRouteGenerator(callable $routeGenerator): self
    {
        $clone = clone $this;
        $clone->routeGenerator = $routeGenerator;

        return $clone;
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
