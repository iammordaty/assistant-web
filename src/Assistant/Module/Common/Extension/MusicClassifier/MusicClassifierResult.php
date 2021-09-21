<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

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
     */
    public function __construct(
        private string $musicalKey,
        private float $bpm,
        private string $chromaprint,
        private string $md5,
        private array $features,
        private array $rawResult,
    ) {
    }

    public static function fromOutputJsonFile(string $filename): self
    {
        $rawResult = json_decode(file_get_contents($filename), true);

        $musicalKey = $rawResult['tonal']['chords_key'] . ' ' . $rawResult['tonal']['chords_scale'];
        $bpm = round($rawResult['rhythm']['bpm'], 1);
        $chromaprint = $rawResult['chromaprint']['string'][0];
        $audioMd5 = $rawResult['metadata']['audio_properties']['md5_encoded'];
        $audioCharacteristics = self::createFeatures($rawResult['highlevel']);

        return new self($musicalKey, $bpm, $chromaprint, $audioMd5, $audioCharacteristics, $rawResult);
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

    /**
     * @link https://jsonblob.com/67586924-f135-11eb-99e5-ffeea52be274
     *
     * @return MusicClassifierFeature[]
     */
    private static function createFeatures(array $rawDescriptors): array
    {
        $calcProbability = static fn($value): int => round($value * 100);

        $feature = [];

        if (isset($rawDescriptors['danceable']) && $rawDescriptors['danceable']['value'] === 'danceable') {
            $feature[] = MusicClassifierFeature::create(
                name: 'danceable',
                type: MusicClassifierFeature::TYPE_DANCEABILITY,
                probability: $calcProbability($rawDescriptors['danceability']['probability']),
            );
        }

        // gender (male, female); (nie występuje zawsze)

        if (isset($rawDescriptors['gender'])) {
            $feature[] = MusicClassifierFeature::create(
                name: $rawDescriptors['gender']['value'],
                type: MusicClassifierFeature::TYPE_VOCAL,
                probability: $calcProbability($rawDescriptors['gender']['probability']),
            );
        }

        // genre_electronic (ambient, dnb, house, techno, trance)

        $feature[] = MusicClassifierFeature::create(
            name: $rawDescriptors['genre_electronic']['value'],
            type: MusicClassifierFeature::TYPE_GENRE,
            probability: $calcProbability($rawDescriptors['genre_electronic']['probability'])
        );

        // mood_acoustic (acoustic, not_acoustic); (nie występuje zawsze)

        if (isset($rawDescriptors['mood_acoustic']) && $rawDescriptors['mood_acoustic']['value'] === 'acoustic') {
            $feature[] = MusicClassifierFeature::create(
                name: 'acoustic',
                type: MusicClassifierFeature::TYPE_MOOD,
                probability: $rawDescriptors['mood_acoustic']['probability']
            );
        }

        // mood_aggressive (aggressive, not_aggressive)

        if ($rawDescriptors['mood_aggressive']['value'] === 'aggressive') {
            $feature[] = MusicClassifierFeature::create(
                name: 'aggressive',
                type: MusicClassifierFeature::TYPE_MOOD,
                probability: $calcProbability($rawDescriptors['mood_aggressive']['probability'])
            );
        }

        // mood_happy (happy, not_happy)

        if ($rawDescriptors['mood_happy']['value'] === 'happy') {
            $feature[] = MusicClassifierFeature::create(
                name: 'happy',
                type: MusicClassifierFeature::TYPE_MOOD,
                probability: $calcProbability($rawDescriptors['mood_happy']['probability'])
            );
        }

        // mood_party (party, not_party)

        if ($rawDescriptors['mood_party']['value'] === 'party') {
            $feature[] = MusicClassifierFeature::create(
                name: 'party',
                type: MusicClassifierFeature::TYPE_MOOD,
                probability: $calcProbability($rawDescriptors['mood_party']['probability'])
            );
        }

        // mood_relaxed (relaxed, not_relaxed)

        if ($rawDescriptors['mood_relaxed']['value'] === 'relaxed') {
            $feature[] = MusicClassifierFeature::create(
                name: 'relaxed',
                type: MusicClassifierFeature::TYPE_MOOD,
                probability: $calcProbability($rawDescriptors['mood_relaxed']['probability'])
            );
        }

        // mood_sad (sad, not_sad)

        if ($rawDescriptors['mood_sad']['value'] === 'sad') {
            $feature[] = MusicClassifierFeature::create(
                name: 'sad',
                type: MusicClassifierFeature::TYPE_MOOD,
                probability: $calcProbability($rawDescriptors['mood_sad']['probability'])
            );
        }

        // moods_mirex (Cluster1, Cluster2, Cluster3, Cluster4, Cluster5)

        $feature[] = MusicClassifierFeature::create(
            name: strtolower($rawDescriptors['moods_mirex']['value']),
            type: MusicClassifierFeature::TYPE_MOOD_CLUSTER,
            probability: $calcProbability($rawDescriptors['moods_mirex']['probability'])
        );

        // timbre (dark, bright)
        // https://en.wikipedia.org/wiki/Timbre

        $feature[] = MusicClassifierFeature::create(
            name: $rawDescriptors['timbre']['value'],
            type: MusicClassifierFeature::TYPE_TONE_COLOR,
            probability: $calcProbability($rawDescriptors['timbre']['probability'])
        );

        // tonal_atonal (tonal, atonal)
        // https://en.wikipedia.org/wiki/Tonality

        $feature[] = MusicClassifierFeature::create(
            name: $rawDescriptors['tonal_atonal']['value'],
            type: MusicClassifierFeature::TYPE_TONALITY,
            probability: $calcProbability($rawDescriptors['tonal_atonal']['probability'])
        );

        // voice_instrumental (voice, instrumental)

        $feature[] = MusicClassifierFeature::create(
            name: $rawDescriptors['voice_instrumental']['value'],
            probability: $calcProbability($rawDescriptors['voice_instrumental']['probability'])
        );

        return $feature;
    }
}
