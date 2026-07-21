<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

/**
 * Wynik działania serwisu essentia-music-classifier (endpoint /process).
 *
 * Struktura odpowiedzi:
 * - bpm: { value, confidence }
 * - key: { value ("C# minor"), confidence }
 * - genre: [ { genre, confidence }, ... ] (EffNet-Discogs)
 * - tags: [ { label, type (tag|mood|instrument), confidence }, ... ]
 * - audio_md5: hash zawartości audio (stabilny przy zmianie nazwy pliku / metadanych)
 */
final class MusicClassifierResult
{
    /**
     * @param string $musicalKey
     * @param float $bpm
     * @param string $md5
     * @param MusicClassifierFeature[] $features
     * @param array $rawResult
     */
    private function __construct(
        private string $musicalKey,
        private float $bpm,
        private string $md5,
        private array $features,
        private array $rawResult,
    ) {
    }

    public static function fromApiResponse(array $rawResult): self
    {
        // bpm lub key mogą być null-em, jeśli analizator zakończył się błędem (partial success)
        if (!isset($rawResult['bpm']['value']) || !isset($rawResult['key']['value'])) {
            throw new MusicClassifierResultIncompleteException($rawResult['errors'] ?? []);
        }

        $musicalKey = $rawResult['key']['value'];
        $bpm = round($rawResult['bpm']['value'], 1);
        $audioMd5 = $rawResult['audio_md5'];
        $features = self::createFeatures($rawResult);

        return new self($musicalKey, $bpm, $audioMd5, $features, $rawResult);
    }

    public function getMusicalKey(): string
    {
        return $this->musicalKey;
    }

    public function getBpm(): float
    {
        return $this->bpm;
    }

    public function getMd5(): string
    {
        return $this->md5;
    }

    public function getFeatures(): array
    {
        return $this->features;
    }

    public function getRawResult(): array
    {
        return $this->rawResult;
    }

    /** @return MusicClassifierFeature[] */
    private static function createFeatures(array $rawResult): array
    {
        $features = [];

        // genre (EffNet-Discogs, 400 stylów, top 10)

        foreach ($rawResult['genre'] ?? [] as $genre) {
            $features[] = MusicClassifierFeature::create($genre['genre'], $genre['confidence']);
        }

        // tags — połączone i zdeduplikowane etykiety z modeli mood, tags oraz instrument

        foreach ($rawResult['tags'] ?? [] as $tag) {
            $features[] = MusicClassifierFeature::create($tag['label'], $tag['confidence']);
        }

        return $features;
    }
}
