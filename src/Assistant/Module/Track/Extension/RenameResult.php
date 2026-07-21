<?php

namespace Assistant\Module\Track\Extension;

use SplFileInfo;

/** Wynik operacji przeniesienia/zmiany nazwy pliku utworu */
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
