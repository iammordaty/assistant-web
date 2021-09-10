<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

// Draft
final class MusicClassifierResult
{
    // przykładowy plik wygenerowany przez: https://jsonblob.com/67586924-f135-11eb-99e5-ffeea52be274
    // https://essentia.upf.edu/streaming_extractor_music.html#music-descriptors
    // zastanowić się jak przechowywać właściwości wysokopoziomowe, takie jak danceability, mood_happy, mood_happy
    // jako flagi: isDanceable, isHappy czy jakoś inaczej?

    private string $key;
    private float $bpm;
    private array $features;
    private string $chromaprint;
    private string $audioMd5;

    public function __construct(private array $rawResult)
    {
        $this->key = $this->rawResult['tonal']['chords_key'] . ' ' . $this->rawResult['tonal']['chords_scale'];
        $this->bpm = round($this->rawResult['rhythm']['bpm'], 1);
        $this->features = $this->rawResult['highlevel'];
        $this->chromaprint = $this->rawResult['chromaprint']['string'][0];
        $this->audioMd5 = $this->rawResult['metadata']['audio_properties']['md5_encoded'];
    }

    public static function fromOutputJsonFile(string $filename): self
    {
        $rawResult = json_decode(file_get_contents($filename), true);

        return new self($rawResult);
    }

    public function getMusicalKey(): string
    {
        return $this->key;
    }

    public function getBpm(): float
    {
        return $this->bpm;
    }

    public function getChromaprint(): mixed
    {
        return $this->chromaprint;
    }

    public function getAudioMd5(): mixed
    {
        return $this->audioMd5;
    }

    public function getRawResult(): array
    {
        return $this->rawResult;
    }
}
