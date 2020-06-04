<?php

namespace Assistant\Module\Mix\Extension\Strategy;

use Assistant\Module\Track\Model\Track;

interface NextTrackStrategy
{
    public function computeMix(array $matrix): array;

    /**
     * @todo: dla zadanych utworów siatka podobieństwa, niezależnie od kolejności, jest taka sama
     *        a więc ta metoda nie powinna być częścią strategii i interfejsu, choć jako pomocnicza metoda
     *        prywatna może istnieć
     *
     * @param Track[] $listing
     * @return array
     */
    public function computeMatrix(array $listing): array;
}