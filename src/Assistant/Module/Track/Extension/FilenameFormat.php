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

    /** Ten sam układ co wyżej, ale z odbudową katalogów - dla porządkowania plików w incoming */
    case NESTED_MULTI_ARTIST_RELEASE = '%artist%/%album%/%track_number%. %artist% - %title%';

    /**
     * Formaty oferowane przy edycji utworu leżącego już w kolekcji. Nie ma tu wariantu
     * odbudowującego katalogi dla zmiennego artysty, bo katalogu wydania nie da się odtworzyć
     * z metadanych pojedynczego utworu.
     *
     * @return self[]
     */
    public static function forCollection(): array
    {
        return [ self::ARTIST_TITLE, self::SINGLE_ARTIST_RELEASE, self::MULTI_ARTIST_RELEASE ];
    }

    /**
     * Formaty oferowane przy zbiorczym porządkowaniu incoming, gdzie odbudowa katalogów jest
     * pożądana, bo pliki dopiero układa się w strukturę.
     *
     * @return self[]
     */
    public static function forIncoming(): array
    {
        return [ self::ARTIST_TITLE, self::SINGLE_ARTIST_RELEASE, self::NESTED_MULTI_ARTIST_RELEASE ];
    }

    public function label(): string
    {
        return match ($this) {
            self::ARTIST_TITLE => 'artysta - tytuł',
            self::SINGLE_ARTIST_RELEASE => 'artysta / album / artysta - nr - tytuł',
            self::MULTI_ARTIST_RELEASE => 'nr. artysta - tytuł',
            self::NESTED_MULTI_ARTIST_RELEASE => 'artysta / album / nr. artysta - tytuł',
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
            self::NESTED_MULTI_ARTIST_RELEASE =>
                'Wydanie z różnymi artystami wraz z odbudową katalogów artysty i albumu.',
        };
    }
}
