<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track\Model\Track;

/**
 * Wynik koordynowanej aktualizacji utworu (TrackUpdateService::update).
 */
final readonly class UpdateResult
{
    public function __construct(
        public Track $track,
        /** @var string[] Katalogi utworzone podczas rename (do zaindeksowania) */
        public array $createdPaths,
        /** @var string[] Puste katalogi pozostałe po rename (do posprzątania) */
        public array $leftoverPaths,
        /** @var string[] Niekrytyczne ostrzeżenia z zapisu tagów ID3 */
        public array $warnings,
    ) {
    }
}
