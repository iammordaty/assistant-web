<?php

namespace Assistant\Module\Mix\Extension\Strategy;

use Assistant\Module\Track\Model\Track;

interface NextTrackStrategy
{
    /**
     * @param Track[] $listing
     * @return void
     */
    public function compute(array $listing): void;

    public function getMix(): array;

    public function getSimilarityGrid(): array;
}
