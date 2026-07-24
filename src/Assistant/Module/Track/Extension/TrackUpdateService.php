<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Collection\Extension\CollectionMaintenanceService;
use Assistant\Module\Track\Model\Track;
use Monolog\Logger;
use SplFileInfo;

/**
 * Koordynuje całą aktualizację utworu z jednego miejsca i w ustalonej kolejności (F2):
 * zapis tagów ID3 -> (opcjonalny) rename pliku -> pojedynczy zapis do DB -> (opcjonalne) BPM/tonacja
 * -> reindeks/sprzątanie kolekcji, tak aby po powrocie DB odzwierciedlała stan na dysku.
 *
 * Docelowa ścieżka jest liczona zawczasu (dry-run), więc konflikt nazwy jest wykrywany zanim
 * cokolwiek zostanie zmodyfikowane (F3). Zapis do DB następuje dopiero po udanej operacji na
 * filesystemie, a jego niepowodzenie kompensujemy przywróceniem pliku (F10).
 */
final readonly class TrackUpdateService
{
    public function __construct(
        private TrackMetadataWriter $trackMetadataWriter,
        private TrackRenameService $trackRenameService,
        private TrackService $trackService,
        private CollectionMaintenanceService $collectionMaintenance,
        private Logger $logger,
    ) {
    }

    public function update(Track $track, UpdateTrackCommand $command): UpdateResult
    {
        $metadata = $command->toMetadata();
        $renameNeeded = $this->isRenameNeeded($track, $command);

        // F3: policz docelową ścieżkę i wykryj konflikt ZANIM zmodyfikujemy plik
        if ($renameNeeded) {
            $target = $this->trackRenameService->resolveCollectionTarget($track, $metadata);

            if ($this->isConflicting($track->getFile(), $target)) {
                throw new TrackUpdateException(
                    sprintf('Nie można zmienić nazwy - plik docelowy już istnieje: %s', $target->getPathname())
                );
            }
        }

        // zapis tagów ID3 w pliku
        try {
            $warnings = $this->trackMetadataWriter->write($track->getFile(), $metadata);
        } catch (\Throwable $e) {
            throw new TrackUpdateException(
                sprintf('Nie udało się zapisać metadanych: %s', $e->getMessage()),
                previous: $e,
            );
        }

        if ($warnings) {
            $this->logger->warning('Metadata written with warnings', [
                'warnings' => $warnings,
                'pathname' => $track->getPathname(),
            ]);
        }

        $updatedTrack = $track;
        $createdPaths = [];
        $leftoverPaths = [];

        if ($renameNeeded) {
            // przeniesienie pliku; tagi są już zapisane, więc nazwę budujemy z tych samych metadanych
            $sourceFile = $track->getFile();

            try {
                $result = $this->trackRenameService->renameToCollectionLayout($track, $metadata);
            } catch (\Throwable $e) {
                throw new TrackUpdateException(
                    sprintf('Nie udało się zmienić nazwy pliku: %s', $e->getMessage()),
                    previous: $e,
                );
            }

            // F3: weryfikacja, że plik faktycznie znajduje się w nowej lokalizacji
            if (!file_exists($result->file->getPathname())) {
                throw new TrackUpdateException('Zmiana nazwy zgłosiła sukces, ale plik docelowy nie istnieje');
            }

            $updatedTrack = $track->withFile($result->file);
            $createdPaths = $result->createdPaths;
            $leftoverPaths = $result->leftoverPaths;

            // F10: pojedynczy zapis do DB po udanej operacji na FS; gdy padnie - kompensujemy rename
            try {
                $this->trackService->save($updatedTrack);
            } catch (\Throwable $e) {
                $this->restoreOriginalFile($result->file, $sourceFile);

                throw new TrackUpdateException(
                    sprintf('Nie udało się zapisać utworu w bazie: %s', $e->getMessage()),
                    previous: $e,
                );
            }
        }

        // B1: obliczenie BPM/tonacji na FINALNEJ ścieżce (po ewentualnym rename)
        if ($command->calculateAudioData) {
            $this->trackMetadataWriter->calculateAudioData($updatedTrack->getFile()->getPathname());
        }

        // reindeks/sprzątanie kolekcji, aby DB odzwierciedlała zmiany na dysku (in-process, sync)
        foreach ($leftoverPaths as $leftoverPath) {
            $this->collectionMaintenance->clean($leftoverPath);
        }

        foreach ($createdPaths as $createdPath) {
            $this->collectionMaintenance->reindex($createdPath);
        }

        $this->collectionMaintenance->reindex($updatedTrack->getPathname());

        // po (synchronicznym) reindeksie DB ma aktualny GUID (pochodną artist+title) - zwracamy świeży
        // stan, by wołający nie musiał sam pobierać utworu ponownie
        $refreshedTrack = $this->trackService->getByPathname($updatedTrack->getPathname()) ?? $updatedTrack;

        return new UpdateResult($refreshedTrack, $createdPaths, $leftoverPaths, $warnings);
    }

    /**
     * Czy potrzebny jest rename - porównujemy pola wpływające na nazwę pliku.
     *
     * @todo docelowo kryterium wyprowadzić z arbitra/DesiredFilename (desired !== current) - F6
     */
    private function isRenameNeeded(Track $track, UpdateTrackCommand $command): bool
    {
        return $command->artist !== $track->getArtist()
            || $command->title !== $track->getTitle()
            || $command->album !== $track->getAlbum()
            || $command->trackNumber !== $track->getTrackNumber();
    }

    private function isConflicting(SplFileInfo $source, SplFileInfo $target): bool
    {
        if (!file_exists($target->getPathname())) {
            return false;
        }

        // ten sam plik fizyczny (różnica tylko w wielkości liter na FS case-insensitive) to nie konflikt
        return realpath($source->getPathname()) !== realpath($target->getPathname());
    }

    /** Best-effort przywrócenie pliku do pierwotnej lokalizacji po nieudanym zapisie do DB */
    private function restoreOriginalFile(SplFileInfo $current, SplFileInfo $original): void
    {
        $originalDir = $original->getPath();

        if (!is_dir($originalDir)) {
            @mkdir($originalDir, 0775, true);
        }

        if (!@rename($current->getPathname(), $original->getPathname())) {
            $this->logger->error('Failed to roll back renamed file after DB save failure', [
                'from' => $current->getPathname(),
                'to' => $original->getPathname(),
            ]);
        }
    }
}
