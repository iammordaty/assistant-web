<?php

namespace Assistant\Module\Track\Extension;

/**
 * Wzorce nazw plików używane przy zmianie nazwy utworu - jedno miejsce, w którym żyją formaty
 * (wcześniej były rozproszone między warstwę zapisu a szablon modala).
 *
 * Wartością przypadku jest sam wzorzec; liczba ukośników decyduje o tym, ile poziomów katalogów
 * format odbudowuje - patrz TrackRenameService::baseDirFor().
 *
 * Żaden z formatów nie jest "standardowy" ani "wyjątkowy" - w Singles oba układy są równoprawne,
 * a o wyborze decyduje sortowanie w katalogu wydania (patrz AGENTS.md).
 */
enum FilenameFormat: string
{
    /** Other: pojedynczy utwór wprost w katalogu miesiąca */
    case ARTIST_TITLE = '%artist% - %title%';

    /** Singles, artysta stały w wydaniu: numer w środku, katalogi artysty i albumu z metadanych */
    case SINGLE_ARTIST_RELEASE = '%artist%/%album%/%artist% - %track_number% - %title%';

    /** Singles, artysta zmienny w wydaniu: numer z przodu (sortowanie), katalog wydania nietknięty */
    case MULTI_ARTIST_RELEASE = '%track_number%. %artist% - %title%';

    public function label(): string
    {
        return match ($this) {
            self::ARTIST_TITLE => 'artysta - tytuł',
            self::SINGLE_ARTIST_RELEASE => 'artysta / album / artysta - nr - tytuł',
            self::MULTI_ARTIST_RELEASE => 'nr. artysta - tytuł',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ARTIST_TITLE => 'Pojedynczy utwór - nazwa pliku bez katalogów.',
            self::SINGLE_ARTIST_RELEASE =>
                'Wydanie jednego artysty - katalogi artysty i albumu budowane są z metadanych.',
            self::MULTI_ARTIST_RELEASE =>
                'Wydanie z różnymi artystami - numer z przodu zachowuje kolejność utworów, '
                . 'katalog wydania pozostaje bez zmian.',
        };
    }

    /** Liczba poziomów katalogów, które format odbudowuje (0 = zmiana samej nazwy pliku) */
    public function directoryLevels(): int
    {
        return substr_count($this->value, '/');
    }
}
