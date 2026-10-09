<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;

/**
 * AI: wybrane cechy z wyniku klasyfikatora Essentia, czytelne przy doborze utworów: nastroje jako
 * prawdopodobieństwo w procentach oraz barwa, wokal i głośność. Pozostałe dane są dostępne w modalu metadanych.
 */
final readonly class TrackCharacter
{
    /** Klasyfikator => [ klasa pozytywna, etykieta ], w kolejności wyświetlania */
    private const array MOODS = [
        'mood_party' => [ 'party', 'Impreza' ],
        'mood_aggressive' => [ 'aggressive', 'Agresja' ],
        'mood_happy' => [ 'happy', 'Radość' ],
        'mood_relaxed' => [ 'relaxed', 'Relaks' ],
        'mood_sad' => [ 'sad', 'Smutek' ],
    ];

    private const array TIMBRES = [
        'dark' => 'ciemna',
        'bright' => 'jasna',
    ];

    private function __construct(
        /** @var array<string, int> etykieta => prawdopodobieństwo w procentach */
        public array $moods,
        public ?string $timbre,
        public ?bool $hasVoice,
        public ?float $loudness,
    ) {
    }

    public static function fromMusicClassifierResult(MusicClassifierResult $result): ?self
    {
        $rawResult = $result->getRawResult();
        $highlevel = $rawResult['highlevel'] ?? [];

        $moods = [];

        foreach (self::MOODS as $classifier => [ $class, $label ]) {
            $probability = $highlevel[$classifier]['all'][$class] ?? null;

            if ($probability !== null) {
                $moods[$label] = (int) round($probability * 100);
            }
        }

        $timbre = self::TIMBRES[$highlevel['timbre']['value'] ?? ''] ?? null;
        $voice = $highlevel['voice_instrumental']['value'] ?? null;
        $loudness = $rawResult['lowlevel']['loudness_ebu128']['integrated'] ?? null;

        if (!$moods && !$timbre && !$voice && $loudness === null) {
            return null;
        }

        return new self(
            $moods,
            $timbre,
            $voice !== null ? $voice === 'voice' : null,
            $loudness !== null ? round($loudness, 1) : null,
        );
    }
}
