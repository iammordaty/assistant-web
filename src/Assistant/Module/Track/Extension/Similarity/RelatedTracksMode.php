<?php

namespace Assistant\Module\Track\Extension\Similarity;

/**
 * AI: preset listy powiązanych utworów, czyli kierunek, w którym ma iść kolejny utwór. Preset wpisuje do
 * formularza dostawców i przesunięty cel (tempo, tonację), a przy wyszukiwaniu dostarcza swoje wagi
 * i tolerancje. Enum wyznacza listę i kolejność presetów (skróty 1–8), a ich parametry muzyczne
 * znajdują się w konfiguracji modułu podobieństwa, żeby można je było stroić bez zmian w kodzie.
 */
enum RelatedTracksMode: string
{
    /** Utwory podobne do bieżącego, z domyślnymi dostawcami */
    case SIMILAR = 'similar';

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

    /** Celowe zerwanie ciągłości: zbliżone tempo, reszta dowolna */
    case RESET = 'reset';

    public function label(): string
    {
        return match ($this) {
            self::SIMILAR => 'Podobne',
            self::LIFT => 'Podnieś',
            self::ENERGY_BOOST => 'Energy boost',
            self::COOL_DOWN => 'Wycisz',
            self::MOOD_SWITCH => 'Zmień nastrój',
            self::STYLE_SWITCH => 'Zmień styl',
            self::PULSE_SWITCH => 'Zmiana pulsu',
            self::RESET => 'Reset',
        };
    }

    /** @noinspection PhpUnused, Used in the template */
    public function description(): string
    {
        return match ($this) {
            self::SIMILAR => 'Utwory podobne do bieżącego: zbliżone tempo, zgodna tonacja, gatunek i rok',
            self::LIFT => 'Tempo +2,5%, kwinta w górę (Camelot +1), w stronę skali durowej. Podnosi energię parkietu',
            self::ENERGY_BOOST => 'Tempo +4%, cały ton w górę (Camelot +2). Wyraźny, jednorazowy skok energii',
            self::COOL_DOWN => 'Tempo −2,5%, kwarta w górę (Camelot −1), w stronę skali molowej. Pogłębia nastrój bez podbijania energii',
            self::MOOD_SWITCH => 'Tonacja równoległa (A ↔ B): te same dźwięki, inna tonika. Zmienia nastrój przy tej samej energii',
            self::STYLE_SWITCH => 'Sąsiedni gatunek, to samo tempo i zgodna tonacja',
            self::PULSE_SWITCH => 'Half-time lub double-time, zgodna tonacja',
            self::RESET => 'Zbliżone tempo, dowolna tonacja i gatunek',
        };
    }
}
