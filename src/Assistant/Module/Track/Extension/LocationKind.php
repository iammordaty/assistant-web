<?php

namespace Assistant\Module\Track\Extension;

use SplFileInfo;

/**
 * Logiczny typ lokalizacji utworu. Jest źródłem decyzji zależnych od struktury katalogów
 * (format nazwy, katalog bazowy), dzięki czemu kontroler i serwisy nie muszą znać tej struktury (F6).
 */
enum LocationKind
{
    case SINGLES;
    case OTHER;
    case INCOMING;
    case UNSUPPORTED;

    /** Deklaratywny format nazwy pliku dla danej lokalizacji (zamiast literałów rozsianych po kodzie) */
    public function filenameFormat(): string
    {
        return match ($this) {
            self::SINGLES => '%artist%/%album%/%artist% - %track_number% - %title%',
            default => '%artist% - %title%',
        };
    }

    /**
     * Katalog bazowy, względem którego budowana jest docelowa ścieżka pliku.
     *
     * Dla Singles struktura to <indexed>/<litera>/<Artist>/<Album>/plik, więc bazą jest katalog
     * literowy (dwa poziomy nad plikiem). Uwaga: litera pozostaje literą źródła - patrz B4 (rezygnacja).
     */
    public function baseDir(SplFileInfo $source): string
    {
        return match ($this) {
            self::SINGLES => dirname($source->getPath(), 2),
            default => $source->getPath(),
        };
    }
}
