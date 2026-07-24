<?php

namespace Assistant\Module\Collection\Extension;

use Assistant\Module\Collection\Task\CleanerTask;
use Assistant\Module\Collection\Task\Indexer\IndexingDateStrategy;
use Assistant\Module\Collection\Task\IndexerTask;
use Assistant\Module\Common\Extension\ConsoleCommandRunner;

/**
 * Operacje utrzymaniowe kolekcji (reindeks, sprzątanie DB) wołane z poziomu aplikacji web -
 * synchronicznie, w tym samym procesie, bez powłoki (zastępuje shell_exec z kontrolera; F8/B11).
 */
final readonly class CollectionMaintenanceService
{
    public function __construct(
        private ConsoleCommandRunner $consoleCommandRunner,
        private IndexerTask $indexerTask,
        private CleanerTask $cleanerTask,
    ) {
    }

    /** Reindeksuje podaną ścieżkę (plik lub katalog) */
    public function reindex(string $pathname): void
    {
        $this->consoleCommandRunner->runSync($this->indexerTask, [
            'pathname' => $pathname,
            '--indexing-date-strategy' => IndexingDateStrategy::FROM_PARENT_PATHNAME->value,
        ]);
    }

    /** Usuwa z bazy nieistniejące już utwory/katalogi spod podanej ścieżki */
    public function clean(string $pathname): void
    {
        $this->consoleCommandRunner->runSync($this->cleanerTask, [
            'pathname' => $pathname,
        ]);
    }
}
