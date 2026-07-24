<?php

namespace Assistant\Module\Track\Extension;

use Normalizer;
use Slim\Http\ServerRequest;

/**
 * Zwalidowane i znormalizowane dane wejściowe z formularza edycji utworu.
 *
 * Puste (po trim) oznacza "usuń tag"
 * @see self::toMetadata
 */
final readonly class UpdateTrackCommand
{
    public function __construct(
        public string $guid,
        public string $artist,
        public string $title,
        public ?string $album,
        public ?int $trackNumber,
        public ?string $publisher,
        public ?string $genre,
        public ?int $year,
        public ?string $initialKey,
        public ?float $bpm,
        public bool $calculateAudioData,
    ) {
    }

    public static function fromRequest(ServerRequest $request): self
    {
        $postData = (array) $request->getParsedBody();

        $artist = self::normalizeString($postData['artist'] ?? null);
        $title = self::normalizeString($postData['title'] ?? null);

        if ($artist === null) {
            throw new \InvalidArgumentException('Pole "Wykonawca" jest wymagane.');
        }

        if ($title === null) {
            throw new \InvalidArgumentException('Pole "Tytuł utworu" jest wymagane.');
        }

        $year = self::normalizeInt($postData['year'] ?? null);

        if ($year !== null && ($year < 1900 || $year > 2100)) {
            throw new \InvalidArgumentException('Nieprawidłowy rok (dozwolony zakres: 1900-2100).');
        }

        $bpm = self::normalizeFloat($postData['bpm'] ?? null);

        if ($bpm !== null && ($bpm < 1 || $bpm > 300)) {
            throw new \InvalidArgumentException('Nieprawidłowe BPM (dozwolony zakres: 1-300).');
        }

        return new self(
            guid: self::normalizeString($postData['guid'] ?? null) ?? '',
            artist: $artist,
            title: $title,
            album: self::normalizeString($postData['album'] ?? null),
            trackNumber: self::normalizeInt($postData['trackNumber'] ?? null),
            publisher: self::normalizeString($postData['publisher'] ?? null),
            genre: self::normalizeString($postData['genre'] ?? null),
            year: $year,
            initialKey: self::normalizeString($postData['initialKey'] ?? null),
            bpm: $bpm,
            calculateAudioData: isset($postData['task:calculate-audio-data']),
        );
    }

    /**
     * Zwraca metadane (tagi ID3v2) do zapisania w pliku.
     *
     * Puste pola przekazywane są jako pusty string. Ponieważ Id3Adapter::writeMetadata zapisuje
     * cały tag id3v2 na nowo (wyłącznie z przekazanych pól), pusty string czyści dany tag -
     * to realizuje semantykę "puste = usuń tag" (B12). Artysta i tytuł są zawsze niepuste (walidacja).
     */
    public function toMetadata(): array
    {
        return [
            TrackMetadataFields::ARTIST => $this->artist,
            TrackMetadataFields::TITLE => $this->title,
            TrackMetadataFields::ALBUM => $this->album ?? '',
            TrackMetadataFields::TRACK_NUMBER => $this->trackNumber ?? '',
            TrackMetadataFields::PUBLISHER => $this->publisher ?? '',
            TrackMetadataFields::GENRE => $this->genre ?? '',
            TrackMetadataFields::YEAR => $this->year ?? '',
            TrackMetadataFields::INITIAL_KEY => $this->initialKey ?? '',
            TrackMetadataFields::BPM => $this->bpm ?? '',
        ];
    }

    /** Przycina, normalizuje do NFC i zwraca null dla wartości pustej (odróżnia "0" od pustego - B6) */
    private static function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);

            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        return $value;
    }

    private static function normalizeInt(mixed $value): ?int
    {
        $value = self::normalizeString($value);

        return $value !== null ? (int) $value : null;
    }

    private static function normalizeFloat(mixed $value): ?float
    {
        $value = self::normalizeString($value);

        return $value !== null ? (float) $value : null;
    }
}
