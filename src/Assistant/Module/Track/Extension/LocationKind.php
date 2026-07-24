<?php

namespace Assistant\Module\Track\Extension;

/**
 * Logiczny typ lokalizacji utworu w strukturze kolekcji. Wyłącznie klasyfikacja - polityka zależna
 * od lokalizacji (format nazwy, katalog bazowy) należy do TrackRenameService.
 */
enum LocationKind
{
    case INCOMING;
    case OTHER;
    case READY;
    case SINGLES;
    case UNSUPPORTED;
}
