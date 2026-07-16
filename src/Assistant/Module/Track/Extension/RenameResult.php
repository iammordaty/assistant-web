<?php

namespace Assistant\Module\Track\Extension;

use SplFileInfo;

/**
 * Wynik operacji przeniesienia/zmiany nazwy pliku utworu.
 *
 * Zastępuje stan trzymany wcześniej w polach TrackRenameService (leftoverPaths/createdPaths),
 * dzięki czemu serwis jest bezstanowy i bezpieczny przy wielokrotnych wywołaniach.
 */
final readonly class RenameResult
{
    public function __construct(
        public SplFileInfo $file,
        /** @var string[] Katalogi utworzone podczas operacji (do zaindeksowania) */
        public array $createdPaths,
        /** @var string[] Puste katalogi pozostałe po przeniesieniu (do posprzątania) */
        public array $leftoverPaths,
    ) {
    }
}
