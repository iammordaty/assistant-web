<?php

namespace Assistant\Module\Track\Extension;

use GuzzleHttp\Psr7\ServerRequest as Psr7ServerRequest;
use PHPUnit\Framework\TestCase;
use Slim\Http\ServerRequest;

final class UpdateTrackCommandTest extends TestCase
{
    public function testParsesAndNormalizesFields(): void
    {
        $command = UpdateTrackCommand::fromRequest($this->request([
            'guid' => 'artist-title',
            'artist' => '  Artist  ',
            'title' => "Title\t",
            'album' => 'Album',
            'trackNumber' => '3',
            'publisher' => 'Label',
            'genre' => 'House',
            'year' => '2020',
            'initialKey' => 'Am',
            'bpm' => '128.5',
        ]));

        self::assertSame('artist-title', $command->guid);
        self::assertSame('Artist', $command->artist);
        self::assertSame('Title', $command->title);
        self::assertSame('Album', $command->album);
        self::assertSame(3, $command->trackNumber);
        self::assertSame('Label', $command->publisher);
        self::assertSame('House', $command->genre);
        self::assertSame(2020, $command->year);
        self::assertSame('Am', $command->initialKey);
        self::assertSame(128.5, $command->bpm);
        self::assertFalse($command->calculateAudioData);
    }

    public function testEmptyOptionalFieldsBecomeNull(): void
    {
        $command = UpdateTrackCommand::fromRequest($this->request([
            'artist' => 'Artist',
            'title' => 'Title',
            'album' => '',
            'publisher' => '   ',
            'genre' => '',
            'year' => '',
            'initialKey' => '',
            'bpm' => '',
        ]));

        self::assertNull($command->album);
        self::assertNull($command->publisher);
        self::assertNull($command->genre);
        self::assertNull($command->year);
        self::assertNull($command->initialKey);
        self::assertNull($command->bpm);
        self::assertNull($command->trackNumber);
    }

    /** B6: "0" jest legalną wartością, nie może być traktowane jak puste */
    public function testZeroTrackNumberIsPreserved(): void
    {
        $command = UpdateTrackCommand::fromRequest($this->request([
            'artist' => 'Artist',
            'title' => 'Title',
            'trackNumber' => '0',
        ]));

        self::assertSame(0, $command->trackNumber);
    }

    public function testMissingArtistThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UpdateTrackCommand::fromRequest($this->request([ 'title' => 'Title' ]));
    }

    public function testMissingTitleThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UpdateTrackCommand::fromRequest($this->request([ 'artist' => 'Artist' ]));
    }

    /** @dataProvider dataInvalidYear */
    public function testInvalidYearThrows(string $year): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UpdateTrackCommand::fromRequest($this->request([
            'artist' => 'Artist',
            'title' => 'Title',
            'year' => $year,
        ]));
    }

    public function dataInvalidYear(): array
    {
        return [ [ '1899' ], [ '2101' ] ];
    }

    /** @dataProvider dataInvalidBpm */
    public function testInvalidBpmThrows(string $bpm): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UpdateTrackCommand::fromRequest($this->request([
            'artist' => 'Artist',
            'title' => 'Title',
            'bpm' => $bpm,
        ]));
    }

    public function dataInvalidBpm(): array
    {
        return [ [ '0' ], [ '301' ] ];
    }

    public function testCalculateAudioDataFlag(): void
    {
        $command = UpdateTrackCommand::fromRequest($this->request([
            'artist' => 'Artist',
            'title' => 'Title',
            'task:calculate-audio-data' => 'true',
        ]));

        self::assertTrue($command->calculateAudioData);
    }

    /** B12: puste pola trafiają do metadanych jako pusty string (czyszczenie taga) */
    public function testToMetadataEmitsEmptyStringForClearedFields(): void
    {
        $command = UpdateTrackCommand::fromRequest($this->request([
            'artist' => 'Artist',
            'title' => 'Title',
            'album' => '',
        ]));

        $metadata = $command->toMetadata();

        self::assertSame('Artist', $metadata[TrackMetadataFields::ARTIST]);
        self::assertSame('Title', $metadata[TrackMetadataFields::TITLE]);
        self::assertSame('', $metadata[TrackMetadataFields::ALBUM]);
        self::assertSame('', $metadata[TrackMetadataFields::GENRE]);
        self::assertArrayHasKey(TrackMetadataFields::BPM, $metadata);
    }

    private function request(array $parsedBody): ServerRequest
    {
        $psrRequest = (new Psr7ServerRequest('POST', '/'))->withParsedBody($parsedBody);

        return new ServerRequest($psrRequest);
    }
}
