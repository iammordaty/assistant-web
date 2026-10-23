<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;

/**
 * AI: awatar utworu w nagłówku stron utworu (kolekcja i Incoming) oraz w module Miksów, który dostaje go
 * z API (TrackEntryDto), więc reguły inicjałów i kolorów istnieją tylko tutaj.
 *
 * Kolor liter i tła wynika z gatunku, nasycenie tła z tempa, a skala tonacji (Camelot A lub B) przyciemnia
 * albo rozjaśnia litery (assistant.css, `.ast-avatar`).
 */
final readonly class TrackAvatar
{
    /** Kolory Tablera dla rodzin gatunków */
    private const array GENRE_COLORS = [
        'blue' => [ 'House', 'Deep House', 'Tech House', 'Afro House', 'Tribal House', 'Acid House', 'UK Garage' ],
        'cyan' => [ 'Progressive House', 'Trance', 'Progressive Trance', 'Psy-Trance', 'Hard Trance' ],
        'purple' => [ 'Techno', 'Hard Techno', 'Minimal' ],
        'orange' => [ 'Electro House', 'Electro', 'Club', 'Dance', 'Hard Dance', 'Hard House' ],
        'teal' => [ 'Indie Dance', 'Electronic' ],
    ];

    /** Kolor gatunków spoza listy oraz utworów bez gatunku */
    private const string DEFAULT_COLOR = 'secondary';

    /** Minimalne tempo (BPM) => nasycenie tła (w procentach); szybszy utwór ma mocniejsze tło */
    private const array TEMPO_TINTS = [
        140 => 36,
        130 => 22,
        120 => 12,
        0 => 5,
    ];

    private function __construct(
        public string $initials,
        public string $color,
        public int $tint,
        /** Skala tonacji w notacji Camelot: A (moll), B (dur) albo null, gdy tonacja jest nieznana */
        public ?string $scale,
    ) {
    }

    public static function fromTrack(Track|IncomingTrack $track): self
    {
        return new self(
            self::getInitials($track->getArtists() ?? [], $track->getTitle() ?? ''),
            self::getColor($track->getGenre()),
            self::getTint($track->getBpm()),
            self::getScale($track->getInitialKey()),
        );
    }

    /** Awatar utworu z miksu, którego nie ma w kolekcji */
    public static function fromGuid(string $guid): self
    {
        $initials = mb_strtoupper(mb_substr(str_replace('-', '', $guid), 0, 2)) ?: '?';

        return new self($initials, self::DEFAULT_COLOR, self::TEMPO_TINTS[0], null);
    }

    /**
     * Zwraca pierwszą literę lub cyfrę wykonawcy i tytułu, np. „Sash! - Encore Une Fois” → „SE”,
     * „Jens - '99 Psycho Strings” → „J9”
     *
     * @param string[] $artists
     */
    public static function getInitials(array $artists, string $title): string
    {
        $artistInitial = self::getFirstCharacter(implode(' ', $artists));
        $titleInitial = self::getFirstCharacter($title);

        return mb_strtoupper($artistInitial . $titleInitial);
    }

    public function toJson(): array
    {
        return [
            'initials' => $this->initials,
            'color' => $this->color,
            'tint' => $this->tint,
            'scale' => $this->scale,
        ];
    }

    private static function getFirstCharacter(string $text): string
    {
        preg_match('/[\p{L}\p{N}]/u', $text, $matches);

        return $matches[0] ?? '';
    }

    private static function getColor(?string $genre): string
    {
        foreach (self::GENRE_COLORS as $color => $genres) {
            if (in_array($genre, $genres, true)) {
                return $color;
            }
        }

        return self::DEFAULT_COLOR;
    }

    private static function getTint(?float $bpm): int
    {
        foreach (self::TEMPO_TINTS as $minBpm => $tint) {
            if ($bpm >= $minBpm) {
                return $tint;
            }
        }

        return self::TEMPO_TINTS[0];
    }

    private static function getScale(?string $initialKey): ?string
    {
        if (!preg_match('/^[0-9]{1,2}([AB])$/', $initialKey ?? '', $matches)) {
            return null;
        }

        return $matches[1];
    }
}
