<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

/** @see /Volumes/web/music-classifier/README.md */
final readonly class MusicClassifierResult
{
    /** Minimalna pewność tagu pokazywanego na stronie utworu */
    private const float TAG_MIN_CONFIDENCE = 0.1;

    private const array SKIPPED_TAGS = [
        'bass',
        'dance',
        'electronic',
        'house',
        'synthesizer',
    ];

    private function __construct(
        private string $audioMd5,
        private float $bpm,
        private string $musicalKey,
        private array $rawResult,
    ) {
    }

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

    /** @return MusicClassifierTag[] */
    public function getTags(): array
    {
        $tags = [];

        foreach ($this->getRawTagsByConfidence() as $rawTag) {
            if ($rawTag['confidence'] < self::TAG_MIN_CONFIDENCE) {
                break;
            }

            $normalizedName = preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($rawTag['label']));

            if (in_array($normalizedName, self::SKIPPED_TAGS, true)) {
                continue;
            }

            $tags[$normalizedName] ??= new MusicClassifierTag(
                mb_strtolower($rawTag['label']),
                $rawTag['type'],
                $rawTag['confidence'],
            );
        }

        return array_values($tags);
    }

    /** @return array<array{label: string, type: string, confidence: float}> */
    private function getRawTagsByConfidence(): array
    {
        $genres = array_map(
            static fn (array $genre): array => [
                'label' => $genre['genre'],
                'type' => MusicClassifierTag::TYPE_GENRE,
                'confidence' => $genre['confidence'],
            ],
            $this->rawResult['genre'],
        );

        $rawTags = [ ...$genres, ...$this->rawResult['tags'] ];

        usort($rawTags, static fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);

        return $rawTags;
    }
}
