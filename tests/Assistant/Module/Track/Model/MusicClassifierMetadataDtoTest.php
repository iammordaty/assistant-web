<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\TestCase;

final class MusicClassifierMetadataDtoTest extends TestCase
{
    public function testStoredDocumentContainsExpectedFields(): void
    {
        $document = $this->createDto()->toStorage();

        self::assertSame(
            [ 'track_guid', 'audio_md5', 'calculated_date', 'bpm', 'musical_key', 'features', 'raw_result' ],
            array_keys($document),
        );
    }

    public function testNormalizedValuesAreTakenFromResult(): void
    {
        $document = $this->createDto()->toStorage();

        self::assertSame('artist-title', $document['track_guid']);
        self::assertSame('md5-encoded', $document['audio_md5']);
        self::assertSame(120.0, $document['bpm']);
        self::assertSame('A minor', $document['musical_key']);
        self::assertInstanceOf(UTCDateTime::class, $document['calculated_date']);
    }

    /** Gatunki i etykiety trafiają do bazy jako jedna lista cech, z pewnością wyrażoną w procentach */
    public function testFeaturesAreStoredAsFlatList(): void
    {
        $document = $this->createDto()->toStorage();

        self::assertSame(
            [
                [ 'name' => 'House', 'probability' => 90.0 ],
                [ 'name' => 'party', 'probability' => 80.0 ],
            ],
            $document['features'],
        );
    }

    /** Pełna odpowiedź serwisu zostaje zapisana bez zmian, ponieważ baza zastępuje plik z wynikiem */
    public function testRawResultIsStoredWithoutChanges(): void
    {
        $document = $this->createDto()->toStorage();

        self::assertSame($this->apiResponse(), $document['raw_result']);
    }

    public function testDocumentFromStorageIsConvertedToPlainArrays(): void
    {
        $calculatedDate = new UTCDateTime();

        $document = new BSONDocument([
            'track_guid' => 'artist-title',
            'audio_md5' => 'md5-encoded',
            'calculated_date' => $calculatedDate,
            'bpm' => 120.0,
            'musical_key' => 'A minor',
            'features' => new BSONArray([
                new BSONDocument([ 'name' => 'House', 'probability' => 90.0 ]),
            ]),
            'raw_result' => new BSONDocument([
                'bpm' => new BSONDocument([ 'value' => 120.0 ]),
            ]),
        ]);

        $dto = MusicClassifierMetadataDto::fromStorage($document);

        self::assertSame('artist-title', $dto->trackGuid);
        self::assertSame($calculatedDate, $dto->calculatedDate);
        self::assertSame([ [ 'name' => 'House', 'probability' => 90.0 ] ], $dto->features);
        self::assertSame([ 'bpm' => [ 'value' => 120.0 ] ], $dto->rawResult);
    }

    /** Dokument zapisany, zanim cechy oraz surowy wynik zaczęły być przechowywane, nie przerywa odczytu */
    public function testMissingOptionalFieldsFallBackToEmptyArrays(): void
    {
        $document = new BSONDocument([
            'track_guid' => 'artist-title',
            'audio_md5' => 'md5-encoded',
            'calculated_date' => new UTCDateTime(),
            'bpm' => 120.0,
            'musical_key' => 'A minor',
        ]);

        $dto = MusicClassifierMetadataDto::fromStorage($document);

        self::assertSame([], $dto->features);
        self::assertSame([], $dto->rawResult);
    }

    /** Postać prezentacyjna różni się od zapisywanej wyłącznie formatem daty */
    public function testPresentationArrayUsesIsoDate(): void
    {
        $dto = $this->createDto();
        $array = $dto->toArray();

        self::assertSame($dto->calculatedDate->toDateTime()->format(DATE_ATOM), $array['calculated_date']);
        self::assertSame(array_keys($dto->toStorage()), array_keys($array));
    }

    private function createDto(): MusicClassifierMetadataDto
    {
        $result = MusicClassifierResult::fromApiResponse($this->apiResponse());

        $dto = MusicClassifierMetadataDto::fromResult('artist-title', $result);

        return $dto;
    }

    /** Minimalna odpowiedź serwisu essentia-music-classifier (endpoint /process) */
    private function apiResponse(): array
    {
        return [
            'bpm' => [ 'value' => 120.04, 'confidence' => 0.9 ],
            'key' => [ 'value' => 'A minor', 'confidence' => 0.8 ],
            'audio_md5' => 'md5-encoded',
            'genre' => [
                [ 'genre' => 'House', 'confidence' => 0.9 ],
            ],
            'tags' => [
                [ 'label' => 'party', 'type' => 'mood', 'confidence' => 0.8 ],
            ],
        ];
    }
}
