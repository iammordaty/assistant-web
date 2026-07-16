<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Breadcrumbs\Breadcrumb;
use Assistant\Module\Common\Extension\Breadcrumbs\BreadcrumbsBuilder;
use Assistant\Module\Common\Extension\Breadcrumbs\UrlGenerator\EmptyRouteGenerator;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Monolog\Logger;
use Normalizer;
use SplFileInfo;

// wyciągnięcie logiki z RenameTrackTask na szybko, totalne yolo, bo o co innego chodziło, nie przywiązywać się;
// wypadałoby to przy którejś okazji ogarnąć zgodnie ze sztuką :-)
final class TrackRenameService
{
    public function __construct(
        private BreadcrumbsBuilder $breadcrumbsBuilder,
        private Config $config,
        private Logger $logger,
        private TrackFilenameSuggestion $trackFilenameSuggestion,
        private TrackService $trackService,
    ) {
    }

    public function clean(Track|IncomingTrack $track): RenameResult
    {
        $target = $this->trackFilenameSuggestion->getSuggestedFilename($track->getFile());

        return $this->move($track->getFile(), new SplFileInfo($target));
    }

    /**
     * Buduje docelową nazwę pliku na podstawie przekazanych metadanych (a nie ponownej analizy pliku)
     * i przenosi tam plik. Źródłem prawdy jest $metadata podane przez wywołującego (F4).
     *
     * @param array $metadata metadane (tagi) w postaci [ pole => wartość ], np. z UpdateTrackCommand::toMetadata()
     */
    public function rename(Track|IncomingTrack $track, string $format, array $metadata, bool $markAsReady): RenameResult
    {
        $target = $this->buildTargetFilename($track, $format, $metadata, $markAsReady);

        return $this->move($track->getFile(), new SplFileInfo($target));
    }

    public function target(Track|IncomingTrack $track, SplFileInfo $target): RenameResult
    {
        return $this->move($track->getFile(), $target);
    }

    /**
     * Liczy docelową (absolutną) ścieżkę dla rename() BEZ efektów ubocznych (bez mkdir/rename/rmdir).
     * Pozwala zawczasu wykryć konflikt nazwy zanim cokolwiek zostanie zapisane (dry-run, F3).
     */
    public function resolveTargetPathname(
        Track|IncomingTrack $track,
        string $format,
        array $metadata,
        bool $markAsReady,
    ): SplFileInfo {
        $target = new SplFileInfo($this->buildTargetFilename($track, $format, $metadata, $markAsReady));

        return $this->resolveAbsoluteTarget($track->getFile(), $target);
    }

    /** Buduje względną nazwę pliku (bez katalogu bazowego) z formatu i metadanych */
    private function buildTargetFilename(
        Track|IncomingTrack $track,
        string $format,
        array $metadata,
        bool $markAsReady,
    ): string {
        // odrzucenie pustych pól; świadome typów, bo $metadata może zawierać int/float/null
        $metadata = array_filter($metadata, static fn ($field) => trim((string) $field) !== '');

        if (empty($metadata)) {
            throw new \RuntimeException('Cannot prepare target filename: no metadata');
        }

        $metadata = array_map(
            static fn ($field) => is_numeric($field) ? $field : self::sanitizeForFilesystem((string) $field),
            $metadata
        );

        if (isset($metadata['track_number']) && (int) $metadata['track_number'] < 10) {
            $metadata['track_number'] = '0' . (int) $metadata['track_number'];
        }

        $placeholders = array_map(static fn ($placeholder) => "%{$placeholder}%", array_keys($metadata));
        $target = strtr($format, array_combine($placeholders, $metadata));

        if (str_contains($target, '%')) {
            preg_match_all('/%[a-z]+%/', $target, $matches);

            $message = sprintf(
                'Cannot prepare target filename: some metadata fields are empty (%s)',
                implode(', ', $matches[0])
            );

            throw new \RuntimeException($message);
        }

        $target .= sprintf('.%s', strtolower($track->getFile()->getExtension()));

        if ($markAsReady) {
            $target = sprintf('%s/%s', basename($this->config->get('collection.ready_dir')), $target);
        }

        return $target;
    }

    /** Dokleja katalog bazowy (wyprowadzony z typu lokalizacji) do względnej nazwy pliku */
    private function resolveAbsoluteTarget(SplFileInfo $source, SplFileInfo $target): SplFileInfo
    {
        $baseDir = $this->trackService->getLocationArbiter()->getLocationKind($source)->baseDir($source);

        return new SplFileInfo(sprintf('%s/%s', $baseDir, $target));
    }

    /**
     * Sanityzuje pojedynczy komponent nazwy pliku (B9).
     *
     * Uwaga: to warstwa nazwy pliku, nie escaping powłoki - nie zastępuje escapeshellarg()
     * przy przekazywaniu ścieżek do komend (patrz F8/B11).
     */
    public static function sanitizeForFilesystem(string $value): string
    {
        $value = str_replace([ '/', ':' ], '-', $value);
        $value = str_replace('"', '\'', $value);
        $value = str_replace([ '*', '?', '<', '>', '|', '\\' ], '', $value);

        // znaki sterujące (\x00-\x1F oraz \x7F)
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        // kolaps wielokrotnych białych znaków do pojedynczej spacji
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        // Windows: brak wiodących/końcowych spacji i kropek
        $value = trim($value, " .");

        // normalizacja Unicode do NFC (istotne na APFS)
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);

            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        // nazwy zarezerwowane w Windows
        if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/i', $value)) {
            $value = '_' . $value;
        }

        // limit długości komponentu (255 znaków)
        if (mb_strlen($value) > 255) {
            $value = mb_substr($value, 0, 255);
        }

        // fallback, gdy po sanityzacji nie zostało nic
        if ($value === '') {
            $value = '_';
        }

        return $value;
    }

    private function move(SplFileInfo $source, SplFileInfo $target): RenameResult
    {
        $logContext = [];

        // F13: kandydatów na puste katalogi trzeba ustalić na podstawie ŹRÓDŁA, zanim je przeniesiemy
        // (po rename ścieżka źródła już nie istnieje, więc arbiter/isReadable dawałby wynik pusty).
        $arbiter = $this->trackService->getLocationArbiter();
        $sourceInCollection = $arbiter->isInCollection($source);
        $cleanupBoundary = $arbiter->getIndexedDir($source);
        $sourceDir = $source->getPath();

        $logContext['source'] = $source->getPathname();
        $logContext['source_in_collection'] = $sourceInCollection;

        $target = $this->resolveAbsoluteTarget($source, $target);

        // Na filesystemach case-insensitive (APFS, HFS+, NTFS) file_exists() zwróci true również gdy
        // zmieniła się wyłącznie wielkość liter w nazwie (bo to fizycznie ten sam plik / inode) - taki
        // przypadek nie jest konfliktem, tylko celem operacji, więc odróżniamy go od realnej kolizji.
        $isSamePhysicalFile = file_exists($target->getPathname())
            && realpath($source->getPathname()) === realpath($target->getPathname());

        if (file_exists($target->getPathname()) && !$isSamePhysicalFile) {
            throw new \RuntimeException("Target {$target->getPathname()} already exists!");
        }

        $modificationTime = $source->getMTime();
        $createdPaths = $this->calculateNonExistentPaths($target);

        $logContext['created_paths'] = $createdPaths;

        if (!file_exists($target->getPath()) && !mkdir($target->getPath(), 0775, true)) {
            throw new \RuntimeException("Can\'t create directory {$target->getPath()}.");
        }

        $logContext['target'] = $target->getPathname();
        $logContext['pathname'] = $target->getPathname();

        // Dwuetapowy rename przez plik tymczasowy w katalogu docelowym. Pośredni krok sprawia, że
        // ścieżki źródłowa i pośrednia realnie się różnią, dzięki czemu zmiana samej wielkości liter
        // przechodzi także na filesystemach case-insensitive. Plik tymczasowy powstaje w katalogu
        // docelowym, aby rename był atomowym move w obrębie tego samego wolumenu.
        $tmp = $target->getPath() . '/.rename-' . bin2hex(random_bytes(8)) . '.tmp';

        if (!@rename($source->getPathname(), $tmp)) {
            $this->removeCreatedPaths($createdPaths);

            throw new \RuntimeException("Can\'t rename {$source->getPathname()} to temporary file.");
        }

        if (!@rename($tmp, $target->getPathname())) {
            @rename($tmp, $source->getPathname()); // rollback pliku do stanu wyjściowego
            $this->removeCreatedPaths($createdPaths);

            throw new \RuntimeException("Can\'t rename {$source->getPathname()} to {$target->getPathname()}.");
        }

        foreach ($createdPaths as $path) {
            touch($path, $modificationTime, $modificationTime);
        }

        $leftoverPaths = [];

        // sprzątamy dopiero po udanym rename (katalogi robią się puste, gdy plik już z nich wyszedł),
        // ale wyłącznie w obrębie kolekcji i nie wyżej niż katalog indeksowany (granica)
        if ($sourceInCollection && $cleanupBoundary !== null) {
            $leftoverPaths = self::removeEmptyDirectoriesUpTo($sourceDir, $cleanupBoundary);

            $logContext['leftover_paths'] = $leftoverPaths;
        }

        $this->logger->debug('Renamed track', $logContext);

        return new RenameResult($target, $createdPaths, $leftoverPaths);
    }

    /**
     * Usuwa katalogi utworzone w bieżącym wywołaniu move() (rollback po nieudanym rename).
     * Cofa tylko te ścieżki, których wcześniej nie było (createdPaths) i tylko jeśli są puste,
     * usuwając od najgłębszej do najpłytszej.
     *
     * @param string[] $createdPaths
     */
    private function removeCreatedPaths(array $createdPaths): void
    {
        foreach (array_reverse($createdPaths) as $path) {
            if (is_dir($path) && !@rmdir($path)) {
                $this->logger->warning('Failed to remove directory during rename rollback', [ 'path' => $path ]);
            }
        }
    }

    private function calculateNonExistentPaths(SplFileInfo $target): array
    {
        $breadcrumbs = $this->breadcrumbsBuilder
            ->withPath($target->getPath())
            ->withRouteGenerator(new EmptyRouteGenerator())
            ->createBreadcrumbs();

        $paths = array_map(fn (Breadcrumb $breadcrumb) => $breadcrumb->pathname, $breadcrumbs);
        $paths = array_filter($paths, fn (string $pathname) => !file_exists($pathname));

        return [ ...$paths ];
    }

    /**
     * Usuwa puste katalogi pozostałe po przeniesieniu pliku - idąc w górę od katalogu źródłowego,
     * dopóki katalog jest pusty i nie jest granicą (katalogiem indeksowanym). Usuwa "w locie", dzięki
     * czemu po skasowaniu podkatalogu jego rodzic również może zostać uznany za pusty (kaskada, B2).
     * Zamiast sztywnych 2 poziomów obsługuje dowolną głębokość struktury Artist/Album/... .
     *
     * Przykład: zmiana artysty/albumu w Singles przenosi plik do innego katalogu, np.
     * z /collection/Singles/x/X and Y/Z/X and Y - Z.mp3
     * na /collection/Singles/x/X feat. Y/Z (Extended Mix)/X feat. Y - Z (Extended Mix).mp3
     * - i to te opuszczone katalogi tutaj kasujemy.
     *
     * @return string[] faktycznie usunięte katalogi (od najgłębszego)
     */
    private static function removeEmptyDirectoriesUpTo(string $startDir, string $boundary): array
    {
        $boundary = rtrim($boundary, '/');
        $removed = [];
        $dir = $startDir;

        while ($dir !== $boundary && self::isDirectoryEmpty($dir)) {
            self::removeJunkFiles($dir);

            if (!@rmdir($dir)) {
                break; // nie udało się usunąć - nie kontynuujemy w górę, by nie zgłaszać nieusuniętych
            }

            $removed[] = $dir;
            $dir = dirname($dir);
        }

        return $removed;
    }

    /** Czy katalog jest "pusty" - odporne na brak katalogu/uprawnień oraz na pliki-śmieci (dotfiles) - B5 */
    private static function isDirectoryEmpty(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $entries = @scandir($path);

        if ($entries === false) {
            return false;
        }

        $entries = array_diff($entries, [ '.', '..' ]);
        $entries = array_filter($entries, static fn (string $name) => !str_starts_with($name, '.'));

        return $entries === [];
    }

    /**
     * Usuwa pliki-śmieci (dotfiles, np. .DS_Store) z katalogu uznanego za pusty (B5), aby rmdir mógł
     * się powieść. Wywoływane wyłącznie dla katalogów, które zawierają już tylko takie pliki.
     */
    private static function removeJunkFiles(string $dir): void
    {
        $entries = @scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && str_starts_with($entry, '.')) {
                @unlink($dir . '/' . $entry);
            }
        }
    }
}
