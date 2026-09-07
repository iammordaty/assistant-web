<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;

final class MusicClassifierMetadataDtoTest extends TestCase
{
    private string $resultFile;

    protected function setUp(): void
    {
        $this->resultFile = sprintf('%s/classifier-%s.json', sys_get_temp_dir(), bin2hex(random_bytes(6)));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->resultFile)) {
            unlink($this->resultFile);
        }
    }

    public function testStoredDocumentContainsSelectedSections(): void
    {
        $document = $this->createDto()->toStorage();

        self::assertSame(
            [ 'track_guid', 'audio_md5', 'calculated_date', 'chromaprint', 'rhythm', 'tonal', 'highlevel' ],
            array_keys($document),
        );
    }

    /** Sekcja `lowlevel` odpowiada za większość objętości wyniku i nie jest zapisywana */
    public function testHeaviestSectionIsNotStored(): void
    {
        $document = $this->createDto([ 'lowlevel' => [ 'average_loudness' => 0.97 ] ])->toStorage();

        self::assertArrayNotHasKey('lowlevel', $document);
    }

    /** Z sekcji `metadata` zachowywany jest wyłącznie md5 audio */
    public function testAudioMd5IsTakenFromMetadataSection(): void
    {
        $document = $this->createDto()->toStorage();

        self::assertArrayNotHasKey('metadata', $document);
        self::assertSame('md5-encoded', $document['audio_md5']);
    }

    public function testTrackGuidAndCalculatedDateAreStored(): void
    {
        $document = $this->createDto()->toStorage();

        self::assertSame('artist-title', $document['track_guid']);
        self::assertInstanceOf(UTCDateTime::class, $document['calculated_date']);
    }

    public function testSectionsAreStoredWithoutChanges(): void
    {
        $document = $this->createDto()->toStorage();

        self::assertSame([ 'chords_key' => 'A', 'chords_scale' => 'minor' ], $document['tonal']);
        self::assertSame([ 'string' => [ 'fingerprint' ] ], $document['chromaprint']);
    }

    /** Wynik bez którejś z zapisywanych sekcji nie przerywa zapisu — brakująca sekcja jest pomijana */
    public function testMissingSectionIsSkipped(): void
    {
        $result = $this->minimalResult();

        unset($result['chromaprint']);

        // MusicClassifierResult wymaga chromaprintu do odczytu, więc sekcja znika dopiero z surowego wyniku
        $dto = new MusicClassifierMetadataDto(
            'artist-title',
            'md5-encoded',
            new UTCDateTime(),
            array_intersect_key($result, array_flip([ 'highlevel', 'rhythm', 'tonal', 'chromaprint' ])),
        );

        $document = $dto->toStorage();

        self::assertArrayNotHasKey('chromaprint', $document);
        self::assertArrayHasKey('tonal', $document);
    }

    private function createDto(array $overrides = []): MusicClassifierMetadataDto
    {
        file_put_contents($this->resultFile, json_encode($this->minimalResult($overrides)));

        $result = MusicClassifierResult::fromResultFile($this->resultFile);

        return MusicClassifierMetadataDto::fromResult('artist-title', $result);
    }

    /**
     * Minimalny wynik klasyfikacji, wzorem MusicClassifierServiceTest::minimalResult() — dokumentuje
     * zestaw kluczy wymagany przez MusicClassifierResult::fromResultFile().
     */
    private function minimalResult(array $overrides = []): array
    {
        return array_replace_recursive([
            'chromaprint' => [
                'string' => [ 'fingerprint' ],
            ],
            'metadata' => [
                'audio_properties' => [
                    'md5_encoded' => 'md5-encoded',
                ],
            ],
            'rhythm' => [
                'bpm' => 120.0,
            ],
            'tonal' => [
                'chords_key' => 'A',
                'chords_scale' => 'minor',
            ],
            'highlevel' => [
                'genre_electronic' => [ 'value' => 'house', 'probability' => 0.9 ],
                'mood_aggressive' => [ 'value' => 'not_aggressive', 'probability' => 0.1 ],
                'mood_happy' => [ 'value' => 'not_happy', 'probability' => 0.1 ],
                'mood_party' => [ 'value' => 'party', 'probability' => 0.8 ],
                'mood_relaxed' => [ 'value' => 'not_relaxed', 'probability' => 0.1 ],
                'mood_sad' => [ 'value' => 'not_sad', 'probability' => 0.1 ],
                'moods_mirex' => [ 'value' => 'Cluster1', 'probability' => 0.5 ],
                'timbre' => [ 'value' => 'dark', 'probability' => 0.5 ],
                'tonal_atonal' => [ 'value' => 'tonal', 'probability' => 0.5 ],
                'voice_instrumental' => [ 'value' => 'instrumental', 'probability' => 0.5 ],
            ],
        ], $overrides);
    }
}
