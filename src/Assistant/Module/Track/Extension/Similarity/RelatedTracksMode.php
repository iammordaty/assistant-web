<?php

namespace Assistant\Module\Track\Extension\Similarity;

/**
 * AI: tryb doboru powiązanych utworów. Planowanie wyszukuje utwory podobne do bieżącego, a pozostałe
 * tryby służą do grania na żywo: przesuwają cel wyszukiwania (tempo, tonację) albo zmieniają wagi
 * dostawców, aby wskazać kierunek zmiany na parkiecie. Enum wyznacza listę i kolejność trybów
 * w przełączniku, a ich parametry muzyczne znajdują się w konfiguracji modułu podobieństwa,
 * żeby można je było stroić bez zmian w kodzie.
 */
enum RelatedTracksMode: string
{
    case PLANNING = 'planning';

    /** Ten sam groove: zbliżone brzmienie, tempo i tonacja */
    case KEEP = 'keep';

    /** Łagodne podbicie energii: nieco szybciej, kwinta w górę, w stronę skali durowej */
    case LIFT = 'lift';

    /** Wyraźny, jednorazowy skok: szybciej i cały ton w górę */
    case ENERGY_BOOST = 'energy-boost';

    /** Uspokojenie parkietu: nieco wolniej, kwarta w górę, w stronę skali molowej */
    case COOL_DOWN = 'cool-down';

    /** Inny nastrój przy tej samej energii: tonacja równoległa (A ↔ B) */
    case MOOD_SWITCH = 'mood-switch';

    /** Sąsiedni gatunek przy zachowaniu tempa i tonacji */
    case STYLE_SWITCH = 'style-switch';

    /** Half-time lub double-time: zmiana stylu przy zgranym pulsie */
    case PULSE_SWITCH = 'pulse-switch';

    /** Ulubione utwory przy zgodnym tempie i tonacji */
    case SAFE_BET = 'safe-bet';

    /** Celowe zerwanie ciągłości: zbliżone tempo, reszta dowolna */
    case RESET = 'reset';

    /**
     * Tryby grania na żywo, w kolejności przełącznika
     *
     * @return self[]
     */
    public static function live(): array
    {
        return array_values(array_filter(self::cases(), fn (self $mode) => $mode !== self::PLANNING));
    }

    public function label(): string
    {
        return match ($this) {
            self::PLANNING => 'Planowanie',
            self::KEEP => 'Utrzymaj',
            self::LIFT => 'Podnieś',
            self::ENERGY_BOOST => 'Energy boost',
            self::COOL_DOWN => 'Wycisz',
            self::MOOD_SWITCH => 'Zmień nastrój',
            self::STYLE_SWITCH => 'Zmień styl',
            self::PULSE_SWITCH => 'Zmiana pulsu',
            self::SAFE_BET => 'Pewniak',
            self::RESET => 'Reset',
        };
    }

    /** @noinspection PhpUnused, Used in the template */
    public function description(): string
    {
        return match ($this) {
            self::PLANNING => 'Utwory podobne do bieżącego',
            self::KEEP => 'Ten sam groove: zbliżone brzmienie, tempo i tonacja',
            self::LIFT => 'Tempo +2,5%, kwinta w górę (Camelot +1), w stronę skali durowej',
            self::ENERGY_BOOST => 'Tempo +4%, cały ton w górę (Camelot +2)',
            self::COOL_DOWN => 'Tempo −2,5%, kwarta w górę (Camelot −1), w stronę skali molowej',
            self::MOOD_SWITCH => 'Tonacja równoległa (A ↔ B), ta sama energia',
            self::STYLE_SWITCH => 'Sąsiedni gatunek, to samo tempo i zgodna tonacja',
            self::PULSE_SWITCH => 'Half-time lub double-time, zgodna tonacja',
            self::SAFE_BET => 'Ulubione utwory o zgodnym tempie i tonacji',
            self::RESET => 'Zbliżone tempo, dowolna tonacja i gatunek',
        };
    }
}
