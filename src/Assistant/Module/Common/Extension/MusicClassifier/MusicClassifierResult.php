<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use SplFileInfo;

/**
 * @link https://essentia.upf.edu/streaming_extractor_music.html#high-level-classifier-models
 * @link https://essentia.upf.edu/streaming_extractor_music.html#music-descriptors
 */
final class MusicClassifierResult
{
    /**
     * @param string $musicalKey
     * @param float $bpm
     * @param string $chromaprint
     * @param string $md5
     * @param MusicClassifierFeature[] $features
     * @param array $rawResult
     * @param SplFileInfo|null $file
     */
    public function __construct(
        private string $musicalKey,
        private float $bpm,
        private string $chromaprint,
        private string $md5,
        private array $features,
        private array $rawResult,
        private ?SplFileInfo $file = null,
    ) {
    }

    public static function fromResultFile(SplFileInfo|string $filename): self
    {
        if (is_string($filename)) {
            $filename = new SplFileInfo($filename);
        }

        $rawResult = json_decode(file_get_contents($filename), true);

        $musicalKey = $rawResult['tonal']['chords_key'] . ' ' . $rawResult['tonal']['chords_scale'];
        $bpm = round($rawResult['rhythm']['bpm'], 1);
        $chromaprint = $rawResult['chromaprint']['string'][0];
        $audioMd5 = $rawResult['metadata']['audio_properties']['md5_encoded'];
        $audioCharacteristics = self::createFeatures($rawResult['highlevel']);

        return new self($musicalKey, $bpm, $chromaprint, $audioMd5, $audioCharacteristics, $rawResult, $filename);
    }

    public function getMusicalKey(): string
    {
        return $this->musicalKey;
    }

    public function getBpm(): float
    {
        return $this->bpm;
    }

    public function getChromaprint(): string
    {
        return $this->chromaprint;
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

    public function getFile(): ?SplFileInfo
    {
        return $this->file;
    }

    /** @return MusicClassifierFeature[] */
    private static function createFeatures(array $rawFeatures): array
    {
        $features = [];

        if (isset($rawFeatures['danceable']) && $rawFeatures['danceable']['value'] === 'danceable') {
            $features[] = MusicClassifierFeature::create(
                'danceable',
                $rawFeatures['danceability']['probability']
            );
        }

        // gender (male, female); (wartość opcjonalna)

        if (isset($rawFeatures['gender'])) {
            $features[] = MusicClassifierFeature::create(
                $rawFeatures['gender']['value'],
                $rawFeatures['gender']['probability']
            );
        }

        // genre_electronic (ambient, dnb, house, techno, trance)

        $features[] = MusicClassifierFeature::create(
            $rawFeatures['genre_electronic']['value'],
            $rawFeatures['genre_electronic']['probability']
        );

        // mood_acoustic (acoustic, not_acoustic); (wartość opcjonalna)

        if (isset($rawFeatures['mood_acoustic']) && $rawFeatures['mood_acoustic']['value'] === 'acoustic') {
            $features[] = MusicClassifierFeature::create(
                'acoustic',
                $rawFeatures['mood_acoustic']['probability']
            );
        }

        // mood_aggressive (aggressive, not_aggressive)

        if ($rawFeatures['mood_aggressive']['value'] === 'aggressive') {
            $features[] = MusicClassifierFeature::create(
                'aggressive',
                $rawFeatures['mood_aggressive']['probability']
            );
        }

        // mood_happy (happy, not_happy)

        if ($rawFeatures['mood_happy']['value'] === 'happy') {
            $features[] = MusicClassifierFeature::create(
                'happy',
                $rawFeatures['mood_happy']['probability']
            );
        }

        // mood_party (party, not_party)

        if ($rawFeatures['mood_party']['value'] === 'party') {
            $features[] = MusicClassifierFeature::create(
                'party',
                $rawFeatures['mood_party']['probability']
            );
        }

        // mood_relaxed (relaxed, not_relaxed)

        if ($rawFeatures['mood_relaxed']['value'] === 'relaxed') {
            $features[] = MusicClassifierFeature::create(
                'relaxed',
                $rawFeatures['mood_relaxed']['probability']
            );
        }

        // mood_sad (sad, not_sad)

        if ($rawFeatures['mood_sad']['value'] === 'sad') {
            $features[] = MusicClassifierFeature::create(
                'sad',
                $rawFeatures['mood_sad']['probability']
            );
        }

        // moods_mirex (Cluster1, Cluster2, Cluster3, Cluster4, Cluster5)

        $features[] = MusicClassifierFeature::create(
            strtolower($rawFeatures['moods_mirex']['value']),
            $rawFeatures['moods_mirex']['probability']
        );

        // timbre (dark, bright)
        // https://en.wikipedia.org/wiki/Timbre

        $features[] = MusicClassifierFeature::create(
            $rawFeatures['timbre']['value'],
            $rawFeatures['timbre']['probability']
        );

        // tonal_atonal (tonal, atonal)
        // https://en.wikipedia.org/wiki/Tonality

        $features[] = MusicClassifierFeature::create(
            $rawFeatures['tonal_atonal']['value'],
            $rawFeatures['tonal_atonal']['probability']
        );

        // voice_instrumental (voice, instrumental)

        $features[] = MusicClassifierFeature::create(
            $rawFeatures['voice_instrumental']['value'],
            $rawFeatures['voice_instrumental']['probability']
        );

        return $features;
    }
}
