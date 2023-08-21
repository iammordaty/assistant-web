<?php

namespace Assistant\Module\Common\Extension;

interface BeatportApiClientInterface
{
    public function charts(array $query): array;

    public function releases(array $query): array;

    public function track(int $trackId): array;

    public function search(array $query): array;
}
