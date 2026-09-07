<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use MongoDB\BSON\UTCDateTime;

/**
 * Metadane z klasyfikatora audio, zapisywane w bazie danych.
 *
 * Pełny wynik klasyfikacji zostaje na dysku, a do bazy trafia jego część nadająca się do dalszego
 * przetwarzania: sekcje opisujące brzmienie, rytm i tonalność oraz odcisk chromaprint. Pomijana jest
 * sekcja `lowlevel`, która odpowiada za większość objętości wyniku, oraz `metadata` — z tej ostatniej
 * zachowywany jest wyłącznie md5 audio, ponieważ pozwala stwierdzić, czy zapisane dane dotyczą wciąż
 * tego samego nagrania.
 */
final readonly class MusicClassifierMetadataDto
{
    /** Sekcje wyniku klasyfikacji zapisywane w bazie */
    private const array STORED_SECTIONS = [ 'highlevel', 'rhythm', 'tonal', 'chromaprint' ];

    public function __construct(
        public string $trackGuid,
        public string $audioMd5,
        public UTCDateTime $calculatedDate,
        public array $sections,
    ) {
    }

    public static function fromResult(string $trackGuid, MusicClassifierResult $result): self
    {
        // sekcje nieobecne w wyniku są pomijane, więc niekompletny wynik nie przerywa zapisu
        $sections = array_intersect_key($result->getRawResult(), array_flip(self::STORED_SECTIONS));

        return new self($trackGuid, $result->getMd5(), new UTCDateTime(), $sections);
    }

    public function toStorage(): array
    {
        return [
            'track_guid' => $this->trackGuid,
            'audio_md5' => $this->audioMd5,
            'calculated_date' => $this->calculatedDate,
            ...$this->sections,
        ];
    }
}
