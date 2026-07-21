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
        private TrackLocationArbiter $locationArbiter,
    ) {
    }

    /** Porządkuje samą nazwę pliku (heurystyka), zostawiając plik tam, gdzie leży */
    public function clean(Track|IncomingTrack $track): RenameResult
    {
        $filename = $this->trackFilenameSuggestion->getSuggestedFilename($track->getFile());
        $target = new SplFileInfo(sprintf('%s/%s', $track->getFile()->getPath(), $filename));

        return $this->moveTo($track, $target);
    }

    /**
     * Buduje docelową nazwę pliku z jawnie podanego formatu i metadanych, po czym przenosi tam plik.
     * Źródłem prawdy jest $metadata podane przez wywołującego (F4), nie ponowna analiza pliku,
     * oraz $format podany przez wywołującego - ta klasa niczego nie zgaduje.
     *
     * @param array $metadata metadane (tagi) w postaci [ pole => wartość ], np. z UpdateTrackCommand::toMetadata()
     */
    public function rename(Track|IncomingTrack $track, string $format, array $metadata, bool $markAsReady): RenameResult
    {
        return $this->moveTo($track, $this->resolveTarget($track, $format, $metadata, $markAsReady));
    }

    public function target(Track|IncomingTrack $track, string $relativeTarget): RenameResult
    {
        return $this->moveTo($track, $this->resolveManualTarget($track, $relativeTarget));
    }

    /**
     * Liczy docelową (absolutną) ścieżkę dla danego formatu BEZ efektów ubocznych, pozwalając
     * pokazać ją w podglądzie i wykryć konflikt nazwy zanim cokolwiek zostanie zapisane (dry-run, F3).
     */
    public function resolveTarget(
        Track|IncomingTrack $track,
        string $format,
        array $metadata,
        bool $markAsReady,
    ): SplFileInfo {
        $relativeTarget = self::normalizeRelativeTarget(
            $this->buildTargetFilename($track, $format, $metadata, $markAsReady)
        );

        // liczba poziomów bierze się z formatu, nie z gotowej nazwy - prefiks katalogu "gotowe"
        // dokładany przez markAsReady nie jest odbudowywanym poziomem struktury
        $baseDir = $this->baseDirFor($track->getFile(), substr_count($format, '/'));

        return new SplFileInfo(sprintf('%s/%s', $baseDir, $relativeTarget));
    }

    /**
     * Liczy docelową (absolutną) ścieżkę dla nazwy podanej wprost - wpisanej ręcznie w formularzu
     * albo przekazanej z CLI. Nazwa jest zawsze względna wobec niezmiennej części ścieżki
     * (getFixedBaseDir), czyli dokładnie tak, jak jest pokazywana w podglądzie - dzięki temu to,
     * co użytkownik widzi, można wprost poprawić i odesłać.
     */
    public function resolveManualTarget(Track|IncomingTrack $track, string $relativeTarget): SplFileInfo
    {
        $relativeTarget = self::normalizeRelativeTarget($relativeTarget);

        return new SplFileInfo(sprintf('%s/%s', $this->getFixedBaseDir($track), $relativeTarget));
    }

    /**
     * Sprowadza ścieżkę względną do postaci, która na pewno zostanie pod katalogiem bazowym.
     *
     * Bez tego obietnica "nie wyjdzie ponad <rok>/<miesiąc>" jest pusta: SplFileInfo nie normalizuje
     * ścieżki, więc segment ".." przetrwałby do rename() i do zapisu w bazie. Dotyczy zarówno nazwy
     * wpisanej ręcznie, jak i formatu - ten drugi bywa przekazany wprost z formularza (modal listy).
     *
     * @throws \InvalidArgumentException gdy ścieżka próbuje wyjść w górę drzewa albo jest pusta
     */
    private static function normalizeRelativeTarget(string $relativeTarget): string
    {
        $segments = explode('/', trim($relativeTarget, '/'));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException(
                    sprintf('Nieprawidłowa nazwa pliku: "%s" - nie może zawierać "." ani "..".', $relativeTarget)
                );
            }
        }

        $segments = array_map(self::sanitizeForFilesystem(...), $segments);
        $normalized = implode('/', $segments);

        if (trim($normalized, '/_') === '') {
            throw new \InvalidArgumentException('Nazwa pliku jest pusta po oczyszczeniu.');
        }

        return $normalized;
    }

    /**
     * Niezmienna część ścieżki utworu: katalog <rok>/<miesiąc> w kolekcji, a poza nią katalog
     * incoming albo "gotowe". Względem niej pokazujemy nazwę w podglądzie i przyjmujemy nazwę
     * wpisaną ręcznie, więc jest to zarazem granica, ponad którą nie wyjdzie żadna zmiana nazwy.
     */
    public function getFixedBaseDir(Track|IncomingTrack $track): string
    {
        return $this->climbBoundaryFor($track->getFile()) ?? $track->getFile()->getPath();
    }

    /** Wykonuje przeniesienie na policzoną wcześniej ścieżkę docelową */
    public function moveTo(Track|IncomingTrack $track, SplFileInfo $absoluteTarget): RenameResult
    {
        return $this->move($track->getFile(), $absoluteTarget);
    }

    /**
     * Katalog bazowy, od którego budowana jest ścieżka docelowa: wchodzimy w górę o jeden poziom
     * na każdy katalog odbudowywany przez format, ale nigdy powyżej granicy.
     *
     * Granicą w kolekcji jest katalog <rok>/<miesiąc>, bo Singles/Other, rok i miesiąc są przy
     * zmianie nazwy nienaruszalne; poza kolekcją - katalog incoming albo "gotowe".
     *
     * Ta jedna reguła odtwarza wszystkie układy nazw: format z dwoma ukośnikami odbudowuje
     * <artysta>/<album> pod katalogiem miesiąca, a format bez ukośników zmienia samą nazwę pliku
     * tam, gdzie plik już leży - i dlatego układ z numerem z przodu (artysta zmienny w wydaniu)
     * zostawia katalog wydania nietknięty.
     */
    private function baseDirFor(SplFileInfo $source, int $directoryLevels): string
    {
        $dir = $source->getPath();
        // ten sam fallback co w getFixedBaseDir(): dla lokalizacji nieobsługiwanej granicą jest
        // katalog pliku, więc format po prostu nie odbudowuje niczego w górę
        $boundary = $this->climbBoundaryFor($source) ?? $dir;

        while ($directoryLevels-- > 0 && $dir !== $boundary && dirname($dir) !== $dir) {
            $dir = dirname($dir);
        }

        return $dir;
    }

    /** Najwyższy katalog, do którego wolno się cofnąć przy budowaniu ścieżki docelowej */
    private function climbBoundaryFor(SplFileInfo $source): ?string
    {
        $dateDir = $this->locationArbiter->getDateDir($source);

        if ($dateDir !== null) {
            return rtrim($dateDir, '/');
        }

        $boundary = match ($this->locationArbiter->getLocationKind($source)) {
            LocationKind::READY => $this->config->get('collection.ready_dir'),
            LocationKind::INCOMING => $this->config->get('collection.incoming_dir'),
            default => null,
        };

        return $boundary !== null ? rtrim($boundary, '/') : null;
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
            throw new \RuntimeException('Nie można zbudować nazwy pliku - brak metadanych.');
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
            // klasa znaków musi obejmować podkreślenie, inaczej %track_number% i %initial_key%
            // nie trafią do komunikatu - a to one najczęściej są puste (B3)
            preg_match_all('/%[a-z_]+%/', $target, $matches);

            $fields = array_map(
                static fn (string $placeholder) => TrackMetadataFields::label(trim($placeholder, '%')),
                $matches[0],
            );

            $message = sprintf(
                'Nie można zbudować nazwy pliku - wymagany format potrzebuje pól: %s.',
                implode(', ', $fields)
            );

            throw new \RuntimeException($message);
        }

        $target .= sprintf('.%s', strtolower($track->getFile()->getExtension()));

        // plik już gotowy nie dostaje drugiego prefiksu - inaczej powstałoby _zrobione/_zrobione
        if ($markAsReady && !$this->locationArbiter->isReady($track->getFile())) {
            $target = sprintf('%s/%s', basename($this->config->get('collection.ready_dir')), $target);
        }

        return $target;
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
        $sourceInCollection = $this->locationArbiter->isInCollection($source);
        $cleanupBoundary = $this->locationArbiter->getIndexedDir($source);
        $sourceDir = $source->getPath();

        $logContext['source'] = $source->getPathname();
        $logContext['source_in_collection'] = $sourceInCollection;

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
