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
 * Format nazwy pliku jest jawnym wejściem (UpdateTrackCommand), nie jest tu zgadywany. Docelowa
 * ścieżka jest liczona zawczasu (dry-run), więc konflikt nazwy jest wykrywany zanim cokolwiek
 * zostanie zmodyfikowane (F3). Zapis do DB następuje dopiero po udanej operacji na filesystemie,
 * a jego niepowodzenie kompensujemy przywróceniem pliku (F10).
 */
final readonly class TrackUpdateService
{
    public function __construct(
        private TrackMetadataWriter $trackMetadataWriter,
        private TrackRenameService $trackRenameService,
        private FilenameFormatSuggester $filenameFormatSuggester,
        private TrackService $trackService,
        private CollectionMaintenanceService $collectionMaintenance,
        private Logger $logger,
    ) {
    }

    public function update(Track $track, UpdateTrackCommand $command): UpdateResult
    {
        $metadata = $command->toMetadata();

        // F3: policz docelową ścieżkę i wykryj konflikt ZANIM zmodyfikujemy plik
        try {
            $target = $this->resolveTargetFor($track, $command);
        } catch (\Throwable $e) {
            throw new TrackUpdateException(
                sprintf('Nie można wyznaczyć nazwy pliku: %s', $e->getMessage()),
                previous: $e,
            );
        }

        $renameNeeded = $this->isRenameNeeded($track, $command, $target);

        if ($renameNeeded && $this->isConflicting($track->getFile(), $target)) {
            throw new TrackUpdateException(
                sprintf('Nie można zmienić nazwy - plik docelowy już istnieje: %s', $target->getPathname())
            );
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
                $result = $this->trackRenameService->moveTo($track, $target);
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
     * Docelowa ścieżka wynikająca z jawnego wyboru użytkownika: wybranego formatu albo nazwy
     * wpisanej ręcznie. Null oznacza jawną rezygnację ze zmiany nazwy.
     *
     * Publiczna, bo tę samą decyzję musi podjąć podgląd nazwy w kontrolerze - inaczej podgląd
     * i zapis liczyłyby ścieżkę różnymi regułami.
     */
    public function resolveTargetFor(Track $track, UpdateTrackCommand $command): ?SplFileInfo
    {
        if ($command->manualTarget !== null) {
            return $this->trackRenameService->resolveManualTarget($track, $command->manualTarget);
        }

        if ($command->format === null) {
            return null;
        }

        return $this->trackRenameService->resolveTarget(
            $track,
            $command->format->value,
            $command->toMetadata(),
            markAsReady: false,
        );
    }

    /**
     * Zmiana nazwy wymaga, by użytkownik faktycznie o nią poprosił: albo zmienił pole
     * uczestniczące w nazwie, albo świadomie wybrał inny format lub nazwę wpisaną ręcznie.
     *
     * Samo porównanie "policzona ścieżka != bieżąca" NIE wystarcza. Nazwa pliku bywa zgodna
     * z zasadą, a mimo to różna od tego, co odtworzyłby format - np. katalog wydania
     * "Hardy Hard presents The Silver Surfer 2003" przy tagu album "The Silver Surfer 2003".
     * Bez tego warunku poprawka samego gatunku odbudowałaby katalogi i skasowała stary,
     * łamiąc zasadę, że pole spoza ścieżki niczego nie przenosi ani nie kasuje.
     */
    private function isRenameNeeded(Track $track, UpdateTrackCommand $command, ?SplFileInfo $target): bool
    {
        if ($target === null || $target->getPathname() === $track->getFile()->getPathname()) {
            return false;
        }

        return $this->isNameMetadataChanged($track, $command)
            || $command->manualTarget !== null
            || $command->format !== $this->filenameFormatSuggester->suggest($track);
    }

    /** Czy zmieniło się którekolwiek pole wchodzące do nazwy pliku */
    private function isNameMetadataChanged(Track $track, UpdateTrackCommand $command): bool
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
