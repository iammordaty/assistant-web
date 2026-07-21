<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use PHPUnit\Framework\TestCase;

final class MusicClassifierResultTest extends TestCase
{
    public function testValuesAreTakenFromApiResponse(): void
    {
        $result = MusicClassifierResult::fromApiResponse($this->apiResponse());

        self::assertSame('A minor', $result->getMusicalKey());
        self::assertSame('md5-encoded', $result->getMd5());
        self::assertSame($this->apiResponse(), $result->getRawResult());
    }

    /** Tempo jest zaokrąglane do jednego miejsca po przecinku */
    public function testBpmIsRounded(): void
    {
        $result = MusicClassifierResult::fromApiResponse($this->apiResponse([
            'bpm' => [ 'value' => 128.449, 'confidence' => 0.9 ],
        ]));

        self::assertSame(128.4, $result->getBpm());
    }

    /** Gatunki i etykiety tworzą jedną listę cech, a pewność wyrażana jest w procentach */
    public function testGenresAndTagsAreMergedIntoFeatures(): void
    {
        $result = MusicClassifierResult::fromApiResponse($this->apiResponse([
            'genre' => [
                [ 'genre' => 'House', 'confidence' => 0.91 ],
                [ 'genre' => 'Deep House', 'confidence' => 0.42 ],
            ],
            'tags' => [
                [ 'label' => 'party', 'type' => 'mood', 'confidence' => 0.8 ],
            ],
        ]));

        $features = array_map(
            static fn (MusicClassifierFeature $feature): array => [
                $feature->getName(),
                $feature->getProbability(),
            ],
            $result->getFeatures(),
        );

        self::assertSame(
            [
                [ 'House', 91.0 ],
                [ 'Deep House', 42.0 ],
                [ 'party', 80.0 ],
            ],
            $features,
        );
    }

    /** Brak gatunków i etykiet nie przerywa odczytu wyniku */
    public function testResultWithoutGenresAndTagsHasNoFeatures(): void
    {
        $rawResult = $this->apiResponse();

        unset($rawResult['genre'], $rawResult['tags']);

        $result = MusicClassifierResult::fromApiResponse($rawResult);

        self::assertSame([], $result->getFeatures());
    }

    /**
     * Analizator zakończony błędem zwraca null zamiast wartości (częściowy sukces), więc taki wynik
     * jest odrzucany razem z komunikatami błędów zwróconymi przez serwis.
     */
    public function testIncompleteResultIsRejectedWithAnalyzerErrors(): void
    {
        $rawResult = $this->apiResponse([
            'bpm' => [ 'value' => null, 'confidence' => null ],
            'errors' => [
                [ 'analyzer' => 'bpm', 'message' => 'extractor failed' ],
            ],
        ]);

        $this->expectException(MusicClassifierResultIncompleteException::class);
        $this->expectExceptionMessage('Music classifier returned an incomplete result: bpm: extractor failed');

        MusicClassifierResult::fromApiResponse($rawResult);
    }

    public function testResultWithoutKeyIsRejected(): void
    {
        $rawResult = $this->apiResponse();

        unset($rawResult['key']);

        $this->expectException(MusicClassifierResultIncompleteException::class);

        MusicClassifierResult::fromApiResponse($rawResult);
    }

    /** Minimalna odpowiedź serwisu essentia-music-classifier (endpoint /process) */
    private function apiResponse(array $overrides = []): array
    {
        return array_replace([
            'bpm' => [ 'value' => 120.0, 'confidence' => 0.9 ],
            'key' => [ 'value' => 'A minor', 'confidence' => 0.8 ],
            'audio_md5' => 'md5-encoded',
            'genre' => [
                [ 'genre' => 'House', 'confidence' => 0.9 ],
            ],
            'tags' => [
                [ 'label' => 'party', 'type' => 'mood', 'confidence' => 0.8 ],
            ],
        ], $overrides);
    }
}
