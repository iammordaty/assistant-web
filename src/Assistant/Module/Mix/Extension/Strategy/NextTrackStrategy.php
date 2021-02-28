<?php

namespace Assistant\Module\Mix\Extension\Strategy;

use Assistant\Module\Track\Model\Track;

interface NextTrackStrategy
{
    public function computeMix(array $matrix): array;

    /**
     * @param Track[] $listing
     * @return array
     */
    public function computeMatrix(array $listing): array;
}