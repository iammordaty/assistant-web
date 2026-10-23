<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;

/**
 * AI: awatar utworu w nagłówku stron utworu (kolekcja i Incoming). Kolor i inicjały są liczone według tych
 * samych reguł co TrackAvatar.jsx w module Miksów, aby ten sam utwór wyglądał tak samo w obu widokach;
 * zmianę reguł trzeba wprowadzić w obu miejscach.
 */
final readonly class TrackAvatar
{
    private function __construct(
        public string $initials,
        public string $cssClass,
    ) {
    }

    public static function fromTrack(Track|IncomingTrack $track): self
    {
        return new self(
            self::getInitials($track->getArtists(), $track->getTitle()),
            self::getCssClass($track->getBpm(), $track->getGenre(), $track->getInitialKey()),
        );
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

    private static function getFirstCharacter(string $text): string
    {
        preg_match('/[\p{L}\p{N}]/u', $text, $matches);

        return $matches[0] ?? '';
    }

    private static function getCssClass(?float $bpm, ?string $genre, ?string $initialKey): string
    {
        $intensity = 0;
        $baseColor = 'blue';

        if ($bpm >= 140) {
            $intensity += 2;
        } elseif ($bpm >= 130) {
            $intensity += 1;
        }

        if (in_array($genre, [ 'Hard Techno', 'Techno' ], true)) {
            $intensity += 2;
            $baseColor = 'red';
        } elseif (in_array($genre, [ 'Trance', 'Progressive Trance', 'Progressive House' ], true)) {
            $baseColor = 'cyan';
        } elseif (in_array($genre, [ 'House', 'Deep House', 'Tech House' ], true)) {
            $intensity += 1;
        } elseif ($genre === 'Electro House') {
            $intensity += 1;
            $baseColor = 'azure';
        }

        if (preg_match('/^[0-9]+A$/', $initialKey ?? '')) {
            $intensity += 1;
        }

        return match (true) {
            $intensity >= 3 => sprintf('bg-%1$s text-%1$s-fg', $baseColor),
            $intensity >= 1 => sprintf('bg-%1$s-lt text-%1$s', $baseColor),
            default => sprintf('text-%s bg-transparent', $baseColor),
        };
    }
}
