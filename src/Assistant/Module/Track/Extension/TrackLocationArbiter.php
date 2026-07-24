<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use SplFileInfo;

final class TrackLocationArbiter
{
    public function __construct(private Config $config)
    {
    }

    public function isInCollection(mixed $file): bool
    {
        $kind = $this->getLocationKind($file);

        return $kind === LocationKind::SINGLES || $kind === LocationKind::OTHER;
    }

    /** Czy plik leży fizycznie w incoming (surowy incoming lub gotowy ready) - oba są poza kolekcją */
    public function isInIncoming(mixed $file): bool
    {
        $kind = $this->getLocationKind($file);

        return $kind === LocationKind::INCOMING || $kind === LocationKind::READY;
    }

    /** Czy plik leży w ready_dir - przetworzony i gotowy do dodania do kolekcji (nie: surowy incoming) */
    public function isReady(mixed $file): bool
    {
        return $this->getLocationKind($file) === LocationKind::READY;
    }

    /**
     * Rozpoznaje logiczny typ lokalizacji pliku na podstawie realnie indeksowanych katalogów
     * (collection.indexed_dirs) oraz katalogu incoming — a nie samego root_dir.
     */
    public function getLocationKind(mixed $file): LocationKind
    {
        $pathname = $this->getPathname($file);

        if ($pathname === null) {
            return LocationKind::UNSUPPORTED;
        }

        // kolejność istotna, od najbardziej szczegółowego: ready_dir zawiera się w incoming_dir,
        // a incoming_dir w root_dir - najpierw więc ready, potem incoming
        if ($this->isWithin($pathname, $this->config->get('collection.ready_dir'))) {
            return LocationKind::READY;
        }

        if ($this->isWithin($pathname, $this->config->get('collection.incoming_dir'))) {
            return LocationKind::INCOMING;
        }

        $indexedDir = $this->matchIndexedDir($pathname);

        if ($indexedDir === null) {
            return LocationKind::UNSUPPORTED;
        }

        // Singles ma zagnieżdżoną strukturę Artist/Album; pozostałe indeksowane katalogi są "płaskie"
        return basename($indexedDir) === 'Singles' ? LocationKind::SINGLES : LocationKind::OTHER;
    }

    /**
     * Zwraca indeksowany katalog zawierający plik (granica dla sprzątania pustych katalogów, B2),
     * albo null gdy plik nie leży w żadnym z indeksowanych katalogów.
     */
    public function getIndexedDir(mixed $file): ?string
    {
        $pathname = $this->getPathname($file);

        return $pathname !== null ? $this->matchIndexedDir($pathname) : null;
    }

    private function matchIndexedDir(string $pathname): ?string
    {
        return array_find(
            (array) $this->config->get('collection.indexed_dirs'),
            fn ($indexedDir) => $this->isWithin($pathname, $indexedDir)
        );
    }

    /** Czy $pathname leży wewnątrz katalogu $dir (z granicą na separatorze, by uniknąć kolizji prefiksów) */
    private function isWithin(string $pathname, string $dir): bool
    {
        return str_starts_with($pathname, rtrim($dir, '/') . '/');
    }

    private function getPathname(mixed $pathname): ?string
    {
        $file = $pathname;

        if (is_string($pathname)) {
            $file = new SplFileInfo($pathname);
        } elseif ($pathname instanceof Track || $pathname instanceof IncomingTrack) {
            $file = $pathname->getFile();
        }

        if (!$file->isReadable()) {
            return null;
        }

        assert($file instanceof SplFileInfo); // na szybko, może lepszy będzie instanceof i exception
        assert($file->isFile());

        return $file->getPathname();
    }
}
