<?php

namespace Assistant\Module\Common\Extension;

final class Route
{
    public function __construct(
        private string $name,
        private array $params = [],
        private array $query = [],
    ) {
    }

    public static function create(
        string $name,
        ?array $params = [],
        ?array $query = [],
    ): self {
        $url = new self($name, $params, $query);

        return $url;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function getParams(): ?array
    {
        return $this->params;
    }

    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;

        return $clone;
    }

    public function withQuery(array $query): self
    {
        $clone = clone $this;
        $clone->query = $query;

        return $clone;
    }

    public function getQuery(): ?array
    {
        return $this->query;
    }
}
