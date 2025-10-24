<?php

namespace Assistant\Module\Collection\Extension\Validator\TrackValidator;

use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;

final class TrackMetadataValidator
{
    public function __construct(private array $validInitialKeys)
    {
    }

    private const REMIX_NOTATIONS = [
        'Bootleg',
        'Dub',
        'Edit',
        'Interpretation',
        'Mix',
        'Re-Edit',
        'Re-Rub',
        'Re-Touch',
        'Reconstruction',
        'Reinterpretation',
        'Remix',
        'Rerub',
        'ReTouch',
        'Revision',
        'Rework',
        'Version',
        'Vision',
    ];

    private const MIN_BPM = 65;
    private const MIN_YEAR = 1980;

    public function __invoke(IncomingTrack|Track $track): TrackValidationResult
    {
        // tu pewnie da się [ ...$this->x(), ...$this->y() ], ale do sprawdzenia

        $errors = array_merge(
            $this->validateRequiredFields($track),
            $this->validateTitleConsistency($track),
            $this->validateTrailingWhitespaces($track),
            $this->validateRemixNotation($track),
            $this->validateAlbumTrackConsistency($track),
            $this->validateYear($track),
            $this->validateInitialKey($track),
            $this->validateBpm($track),
            $this->validateTrackNumber($track),
        );

        $isValid = count($errors) === 0;

        return new TrackValidationResult($isValid, $errors);
    }

    private function validateRequiredFields(IncomingTrack|Track $track): array
    {
        $errors = [];

        if (!$track->getArtist()) {
            $errors[] = 'Utwór nie zawiera danych o wykonawcy.';
        }

        if (!$track->getTitle()) {
            $errors[] = 'Utwór nie zawiera danych o tytule.';
        }

        if (!$track->getGenre()) {
            $errors[] = 'Utwór nie zawiera danych o gatunku.';
        }

        if (!$track->getInitialKey()) {
            $errors[] = 'Utwór nie zawiera danych o tonacji.';
        }

        if (!$track->getBpm()) {
            $errors[] = 'Utwór nie zawiera danych o liczbie uderzeń na minutę.';
        }

        if (!$track->getYear()) {
            $errors[] = 'Utwór nie zawiera danych o roku wydania.';
        }

        return $errors;
    }

    private function validateTitleConsistency(IncomingTrack|Track $track): array
    {
        $errors = [];

        preg_match_all('/(?<!\p{L})(\p{L}\p{M}*[\p{L}\p{M}\d\p{P}\p{S}\']*)/u', $track->getTitle(), $wordMatches);

        $words = $wordMatches[1] ?? [];

        if (!$words) {
            return [];
        }

        $hasUppercase = false;
        $nonCapitalizedWords = [];

        foreach ($words as $word) {
            $firstChar = $word[0];

            if (mb_strtoupper($firstChar) === $firstChar && $firstChar !== mb_strtolower($firstChar)) {
                $hasUppercase = true;
            } else {
                $nonCapitalizedWords[] = $word;
            }
        }

        if ($hasUppercase && count($nonCapitalizedWords) >= 1) {
            $errors[] = sprintf(
                'Tytuł utworu "%s" wygląda na zapisany niekonsekwentnie. Znaleziono: %s.',
                $track->getTitle(),
                implode(', ', $nonCapitalizedWords)
            );
        }

        return $errors;
    }

    private function validateTrailingWhitespaces(IncomingTrack|Track $track): array
    {
        $errors = [];

        if ($track->getArtist() !== trim($track->getArtist())) {
            $errors[] = 'Pole wykonawca utworu zawiera nadmiarowe spacje.';
        }

        if ($track->getTitle() !== trim($track->getTitle())) {
            $errors[] = 'Tytuł utworu zawiera nadmiarowe spacje.';
        }

        if ($track->getInitialKey() !== trim($track->getInitialKey())) {
            $errors[] = 'Pole tonacji utworu zawiera nadmiarowe spacje.';
        }

        if ($track->getGenre() !== trim($track->getGenre())) {
            $errors[] = 'Pole gatunku utworu zawiera nadmiarowe spacje.';
        }

        if ($track->getPublisher() !== null && $track->getPublisher() !== trim($track->getPublisher())) {
            $errors[] = 'Pole wydawcy zawiera nadmiarowe spacje.';
        }

        return $errors;
    }

    private function validateRemixNotation(IncomingTrack|Track $track): array
    {
        $errors = [];

        foreach (self::REMIX_NOTATIONS as $notation) {
            if (stripos($track->getTitle(), sprintf(' %s)', $notation)) !== false) {
                $errors[] = 'Remiks w tytule musi zawierać się w nawiasach kwadratowych. Znaleziono: ' . $notation;

                break;
            }
        }

        return $errors;
    }

    private function validateAlbumTrackConsistency(IncomingTrack|Track $track): array
    {
        $errors = [];

        if (!$track->getAlbum() && $track->getTrackNumber()) {
            $errors[] = 'Brak numeru ścieżki w albumie.';
        }

        if (!$track->getTrackNumber() && $track->getAlbum()) {
            $errors[] = 'Utwór zawiera numer ścieżki bez albumu.';
        }

        return $errors;
    }

    private function validateYear(IncomingTrack|Track $track): array
    {
        $errors = [];

        if ($track->getYear() < self::MIN_YEAR) {
            $errors[] = sprintf('Coś dziwnego, rok powinien być większy niż %d.', self::MIN_YEAR);
        }

        return $errors;
    }

    private function validateInitialKey(IncomingTrack|Track $track): array
    {
        $errors = [];

        if (!in_array($track->getInitialKey(), $this->validInitialKeys)) {
            $errors[] = sprintf('Nieprawidłowa tonacja (%s).', $track->getInitialKey());
        }

        return $errors;
    }

    private function validateBpm(IncomingTrack|Track $track): array
    {
        $errors = [];

        if ($track->getBpm() !== null && $track->getBpm() < self::MIN_BPM) {
            $errors[] = sprintf(
                'Coś dziwnego, liczba uderzeń na minutę powinna być większa niż %d, jest: %s.',
                self::MIN_BPM,
                $track->getBpm()
            );
        }

        return $errors;
    }

    private function validateTrackNumber(IncomingTrack|Track $track): array
    {
        $errors = [];

        if ($track->getTrackNumber() !== null && $track->getTrackNumber() <= 0) {
            $errors[] = sprintf(
                'Coś dziwnego, numer utworu nie może być mniejszy lub równy zeru, jest: %s',
                $track->getTrackNumber()
            );
        }

        if ($track->isTrackNumberHasLeadingZero()) {
            $errors[] = 'Numeru utworu nie może zawierać wiodącego zera';
        }

        return $errors;
    }
}
