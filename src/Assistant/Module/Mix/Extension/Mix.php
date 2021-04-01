<?php

namespace Assistant\Module\Mix\Extension;

use Assistant\Module\Mix\Extension\Strategy\NextTrackStrategy;
use Assistant\Module\Track\Model\Track;

final class Mix
{
    private array $mix;

    private array $similarityGrid;

    /**
     * @param NextTrackStrategy $nextTrackStrategy
     * @param Track[] $listing
     */
    public function __construct(NextTrackStrategy $nextTrackStrategy, array $listing)
    {
        $nextTrackStrategy->compute($listing);

        $this->mix = $nextTrackStrategy->getMix();

        /** 
         * Do przemyślenia: może interfejs i strategia powinny wystawiać tylko metodę
         * compare($trackOne, $trackTwo): int / getSimilarityValue($trackOne, $trackTwo): int
         * a całą iterację (logikę w getSimilarityGrid) powinna zawierać metoda getSimilarityGrid w tej klasie
         * plus komentarz nr 2 w MostSimilarTrackStrategy::computeSimilarityGrid()
         * 
         * @see MostSimilarTrackStrategy::computeSimilarityGrid()
         */
        $this->similarityGrid = $nextTrackStrategy->getSimilarityGrid();
    }

    public function getMix(): array
    {
        return $this->mix;
    }

    public function getSimilarityGrid(): array
    {
        return $this->similarityGrid;
    }
}
