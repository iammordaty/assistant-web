<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

/** @see /Volumes/web/music-classifier/README.md */
final readonly class MusicClassifierResult
{
    /** Minimalna pewność cechy pokazywanej jako hashtag */
    private const float HASHTAG_MIN_CONFIDENCE = 0.3;

    /** Maksymalna liczba hashtagów, żeby wiersz pozostał czytelny */
    private const int HASHTAG_LIMIT = 8;

    /**
     * AI: cechy wykrywane w ponad 80% utworów kolekcji (116 utworów z 2026 r.), które nie odróżniają
     * jednego utworu od drugiego, a zajmowałyby miejsce w limicie hashtagów
     */
    private const array SKIPPED_HASHTAGS = [ '#bass', '#dance', '#electronic', '#synthesizer' ];

    private function __construct(
        private string $audioMd5,
        private float $bpm,
        private string $musicalKey,
        private array $rawResult,
    ) {
    }

    /**
     * AI: wynik z błędem któregokolwiek analizatora jest odrzucany w całości, bo częściowy wynik byłby
     * nieodróżnialny od kompletnego; klasyfikator nie zapisuje błędów w cache, więc kolejne wywołanie
     * ponowi obliczenia.
     */
    public static function fromResponse(array $rawResult): self
    {
        if (!empty($rawResult['errors'])) {
            $analyzers = array_column($rawResult['errors'], 'analyzer');

            throw new MusicClassifierException(sprintf('Music classifier failed: %s', implode(', ', $analyzers)));
        }

        return new self(
            $rawResult['audio_md5'],
            round($rawResult['bpm']['value'], 1),
            $rawResult['key']['value'],
            $rawResult,
        );
    }

    public function getAudioMd5(): string
    {
        return $this->audioMd5;
    }

    public function getBpm(): float
    {
        return $this->bpm;
    }

    public function getMusicalKey(): string
    {
        return $this->musicalKey;
    }

    public function getRawResult(): array
    {
        return $this->rawResult;
    }

    /**
     * AI: hashtagi pokazują tylko cechy, których model jest wyraźnie pewny. Wartości confidence są względne
     * i w większości bliskie zeru, więc bez progu lista byłaby szumem.
     *
     * Pomijane są cechy z SKIPPED_HASHTAGS. Gatunki i tagi są sortowane razem według pewności, a powtórzenia
     * (np. gatunek „House” i tag „house”) scalane do pierwszego, czyli najpewniejszego, wystąpienia.
     *
     * @return array<string, array{type: string, confidence: float}> hashtag => typ cechy (genre, instrument,
     *     mood, tag) i jej pewność
     */
    public function getHashtags(): array
    {
        $hashtags = [];

        foreach ($this->getFeaturesByConfidence() as $feature) {
            if ($feature['confidence'] < self::HASHTAG_MIN_CONFIDENCE) {
                break;
            }

            $hashtag = '#' . preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($feature['label']));

            if (in_array($hashtag, self::SKIPPED_HASHTAGS, true)) {
                continue;
            }

            $hashtags[$hashtag] ??= [
                'type' => $feature['type'],
                'confidence' => $feature['confidence'],
            ];

            if (count($hashtags) === self::HASHTAG_LIMIT) {
                break;
            }
        }

        return $hashtags;
    }

    /** @return array<array{label: string, type: string, confidence: float}> */
    private function getFeaturesByConfidence(): array
    {
        $genres = array_map(
            static fn (array $genre): array => [
                'label' => $genre['genre'],
                'type' => 'genre',
                'confidence' => $genre['confidence'],
            ],
            $this->rawResult['genre'],
        );

        $features = [ ...$genres, ...$this->rawResult['tags'] ];

        usort($features, static fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);

        return $features;
    }
}
