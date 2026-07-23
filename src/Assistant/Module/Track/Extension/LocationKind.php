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
    case READY;
    case UNSUPPORTED;

    public function filenameFormat(): string
    {
        return match ($this) {
            self::SINGLES => '%artist%/%album%/%artist% - %track_number% - %title%',
            self::OTHER => '%artist% - %title%',
            
            default => '%artist% - %title%',
        };
    }

    public function baseDir(SplFileInfo $source): string
    {
        return match ($this) {
            self::SINGLES => dirname($source->getPath(), 2),
            default => $source->getPath(),
        };
    }
}
