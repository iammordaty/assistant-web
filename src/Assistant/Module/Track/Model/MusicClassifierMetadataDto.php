<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierFeature;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

/**
 * Metadane z klasyfikatora audio, zapisywane w bazie danych.
 *
 * Baza danych jest jedynym miejscem przechowywania wyniku klasyfikacji. Obok pól znormalizowanych,
 * nadających się do dalszego przetwarzania (tempo, tonacja, cechy), zapisywana jest pełna odpowiedź
 * serwisu, dzięki czemu wynik pozostaje dostępny bez ponownej analizy utworu. Md5 audio pozwala
 * stwierdzić, czy zapisane dane dotyczą wciąż tego samego nagrania.
 */
final readonly class MusicClassifierMetadataDto
{
    /** @param array<int, array{name: string, probability: float}> $features */
    public function __construct(
        public string $trackGuid,
        public string $audioMd5,
        public UTCDateTime $calculatedDate,
        public float $bpm,
        public string $musicalKey,
        public array $features,
        public array $rawResult,
    ) {
    }

    public static function fromResult(string $trackGuid, MusicClassifierResult $result): self
    {
        $features = array_map(
            static fn (MusicClassifierFeature $feature): array => [
                'name' => $feature->getName(),
                'probability' => $feature->getProbability(),
            ],
            $result->getFeatures(),
        );

        return new self(
            $trackGuid,
            $result->getMd5(),
            new UTCDateTime(),
            $result->getBpm(),
            $result->getMusicalKey(),
            $features,
            $result->getRawResult(),
        );
    }

    public static function fromStorage(BSONDocument $document): self
    {
        $data = self::toPlainValue($document);

        return new self(
            $data['track_guid'],
            $data['audio_md5'],
            $data['calculated_date'],
            $data['bpm'],
            $data['musical_key'],
            $data['features'] ?? [],
            $data['raw_result'] ?? [],
        );
    }

    public function toStorage(): array
    {
        return [
            'track_guid' => $this->trackGuid,
            'audio_md5' => $this->audioMd5,
            'calculated_date' => $this->calculatedDate,
            'bpm' => $this->bpm,
            'musical_key' => $this->musicalKey,
            'features' => $this->features,
            'raw_result' => $this->rawResult,
        ];
    }

    /** Reprezentacja przeznaczona do prezentacji, z datą zapisaną w formacie ISO 8601 */
    public function toArray(): array
    {
        return [
            ...$this->toStorage(),
            'calculated_date' => $this->calculatedDate->toDateTime()->format(DATE_ATOM),
        ];
    }

    /**
     * Zamienia dokument bazodanowy na zwykłe tablice. Sekcja `raw_result` ma dowolną strukturę,
     * więc konwersja musi obejmować całe zagnieżdżenie, a nie tylko najwyższy poziom.
     */
    private static function toPlainValue(mixed $value): mixed
    {
        if ($value instanceof BSONDocument || $value instanceof BSONArray) {
            $value = $value->getArrayCopy();
        }

        if (is_array($value)) {
            return array_map(self::toPlainValue(...), $value);
        }

        return $value;
    }
}
