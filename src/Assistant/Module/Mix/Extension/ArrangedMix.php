<?php

namespace Assistant\Module\Mix\Extension;

use Assistant\Module\Mix\Extension\Strategy\NextTrackStrategy;
use Assistant\Module\Track\Model\Track;

class ArrangedMix
{
    private array $matrix;

    private array $mix;

    /**
     * @param NextTrackStrategy $nextTrackStrategy
     * @param Track[] $listing
     */
    public function __construct(NextTrackStrategy $nextTrackStrategy, array $listing)
    {
        $this->matrix = $nextTrackStrategy->computeMatrix($listing);
        $this->mix = $nextTrackStrategy->computeMix($this->matrix);
    }

    public function getMix(): array
    {
        return $this->mix;
    }

    public function getMatrix(): array
    {
        return $this->matrix;
    }
}
