<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\MusicClassifierMetadataDto;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\MusicClassifierMetadataRepository;
use DateTime;
use KeyTools\KeyTools;
use MongoDB\BSON\UTCDateTime;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MusicClassifierMetadataCalculatorTaskTest extends TestCase
{
    private const string TRACK_GUID = 'artist-01-track';

    private string $root;
    private string $collectionDir;

    private TestHandler $logHandler;
    private Logger $logger;
    private Config $config;

    /** @var MusicClassifierService&MockObject */
    private MusicClassifierService $musicClassifierService;

    /** @var MusicClassifierMetadataRepository&MockObject */
    private MusicClassifierMetadataRepository $musicClassifierMetadataRepository;

    /** @var TrackService&MockObject */
    private TrackService $trackService;

    private KeyTools $keyTools;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/classifier-metadata-' . bin2hex(random_bytes(6));
        $this->collectionDir = $this->root . '/collection';

        mkdir($this->collectionDir . '/Singles/2020/01. styczen/Artist/Release', 0775, true);

        $this->logHandler = new TestHandler();
        $this->logger = new Logger('test', [ $this->logHandler ]);

        $this->config = new Config([
            'collection' => [
                'root_dir' => $this->collectionDir,
                'indexed_dirs' => [
                    $this->collectionDir . '/Singles',
                    $this->collectionDir . '/Other',
                ],
            ],
        ]);

        $this->musicClassifierService = $this->createMock(MusicClassifierService::class);
        $this->musicClassifierMetadataRepository = $this->createMock(MusicClassifierMetadataRepository::class);
        $this->trackService = $this->createMock(TrackService::class);
        $this->keyTools = KeyTools::fromNotation(KeyTools::NOTATION_MUSICAL_ESSENTIA);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testStatOptionDisplaysCounts(): void
    {
        $this->createTrackFile();

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('count')
            ->willReturn(5);

        $tester = $this->createTester();
        $tester->execute([ '--stat' => true ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('MP3 files in collection: 1', $output);
        self::assertStringContainsString('Metadata records in database: 5', $output);
    }

    /** Metadane utworu, którego nie ma już w indeksie kolekcji, są usuwane z bazy */
    public function testCleanRemovesMetadataOfTracksMissingFromCollection(): void
    {
        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('getTrackGuids')
            ->willReturn([ 'indexed-track', 'orphaned-track' ]);

        $this->trackService
            ->method('getByGuid')
            ->willReturnCallback(
                fn (string $guid): ?Track => $guid === 'indexed-track' ? $this->createTrack() : null,
            );

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('removeByTrackGuids')
            ->with([ 'orphaned-track' ])
            ->willReturn(1);

        $tester = $this->createTester();
        $tester->execute([ '--clean' => true ]);

        self::assertStringContainsString('Cleaned 1 orphaned metadata record(s).', $tester->getDisplay());
    }

    public function testDefaultSkipsTracksWithStoredMetadata(): void
    {
        $pathname = $this->createTrackFile();

        $this->trackService
            ->expects(self::once())
            ->method('createFromFile')
            ->with($pathname)
            ->willReturn($this->createTrack($pathname));

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('getByTrackGuid')
            ->with(self::TRACK_GUID)
            ->willReturn($this->createMetadata());

        $this->musicClassifierService
            ->expects(self::never())
            ->method('analyze');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testSkipNotCalculatedOptionSkipsTracksWithoutStoredMetadata(): void
    {
        $pathname = $this->createTrackFile();

        $this->trackService
            ->method('createFromFile')
            ->willReturn($this->createTrack($pathname));

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('getByTrackGuid')
            ->willReturn(null);

        $this->musicClassifierService
            ->expects(self::never())
            ->method('analyze');

        $tester = $this->createTester();
        $tester->execute([ '--skip-not-calculated' => true ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testForceOptionRecalculatesEvenWhenMetadataIsStored(): void
    {
        $pathname = $this->createTrackFile();

        $this->trackService
            ->method('createFromFile')
            ->willReturn($this->createTrack($pathname));

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('getByTrackGuid')
            ->willReturn($this->createMetadata());

        $this->musicClassifierService
            ->expects(self::once())
            ->method('analyze')
            ->willReturn($this->createClassifierResult());

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('save');

        $tester = $this->createTester();
        $tester->execute([ '--force' => true ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testCalculatedMetadataIsSavedToRepository(): void
    {
        $pathname = $this->createTrackFile();

        $this->trackService
            ->method('createFromFile')
            ->willReturn($this->createTrack($pathname));

        $this->musicClassifierMetadataRepository
            ->method('getByTrackGuid')
            ->willReturn(null);

        $this->musicClassifierService
            ->expects(self::once())
            ->method('analyze')
            ->willReturn($this->createClassifierResult());

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (MusicClassifierMetadataDto $dto): bool {
                return $dto->trackGuid === self::TRACK_GUID && $dto->audioMd5 === 'md5-encoded';
            }));

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testDryRunCalculatesMetadataWithoutSavingIt(): void
    {
        $pathname = $this->createTrackFile();

        $this->trackService
            ->method('createFromFile')
            ->willReturn($this->createTrack($pathname));

        $this->musicClassifierMetadataRepository
            ->method('getByTrackGuid')
            ->willReturn(null);

        $this->musicClassifierService
            ->expects(self::once())
            ->method('analyze')
            ->willReturn($this->createClassifierResult());

        $this->musicClassifierMetadataRepository
            ->expects(self::never())
            ->method('save');

        $tester = $this->createTester();
        $tester->execute([ '--dry-run' => true ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testRespectsLimitOption(): void
    {
        $this->createTrackFile('Artist - 01 - Track 1.mp3');
        $this->createTrackFile('Artist - 02 - Track 2.mp3');

        $this->trackService
            ->method('createFromFile')
            ->willReturn($this->createTrack());

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('getByTrackGuid')
            ->willReturn($this->createMetadata());

        $tester = $this->createTester();
        $tester->execute([ '--limit' => 1 ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testMismatchLoggingWhenBpmOrKeyDiffers(): void
    {
        $pathname = $this->createTrackFile();

        // utwór w bazie: 128.0 bpm, tonacja 11B; wynik klasyfikatora: 120.0 bpm, A minor (8A)
        $track = $this->createTrack($pathname, 128.0, '11B');

        $this->trackService
            ->method('createFromFile')
            ->willReturn($track);

        $this->musicClassifierMetadataRepository
            ->method('getByTrackGuid')
            ->willReturn(null);

        $this->musicClassifierService
            ->method('analyze')
            ->willReturn($this->createClassifierResult());

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertTrue($this->logHandler->hasInfoThatContains('Track bpm differs from calculated value'));
        self::assertTrue($this->logHandler->hasInfoThatContains('Track initial key differs from calculated value'));
    }

    public function testAbortsWhenClassifierFailsTooOften(): void
    {
        // więcej plików niż wynosi próg bezpiecznika, żeby wymusić serię błędów klasyfikatora
        for ($i = 1; $i <= 12; $i++) {
            $this->createTrackFile(sprintf('Artist - %02d - Track %d.mp3', $i, $i));
        }

        $this->trackService
            ->method('createFromFile')
            ->willReturn($this->createTrack());

        // brak metadanych w bazie, więc task próbuje je wyliczyć, a analyze zawsze rzuca wyjątkiem
        $this->musicClassifierMetadataRepository
            ->method('getByTrackGuid')
            ->willReturn(null);

        $this->musicClassifierService
            ->method('analyze')
            ->willThrowException(new MusicClassifierException('essentia is down'));

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertTrue(
            $this->logHandler->hasCriticalThatContains('music classifier (essentia) is failing too often'),
        );
    }

    private function createTester(): CommandTester
    {
        $task = new MusicClassifierMetadataCalculatorTask(
            $this->logger,
            $this->musicClassifierService,
            $this->musicClassifierMetadataRepository,
            $this->trackService,
            $this->keyTools,
            $this->config,
        );

        return new CommandTester($task);
    }

    /** Tworzy plik utworu w kolekcji i zwraca jego ścieżkę */
    private function createTrackFile(string $basename = 'Artist - 01 - Track.mp3'): string
    {
        $pathname = sprintf('%s/Singles/2020/01. styczen/Artist/Release/%s', $this->collectionDir, $basename);

        file_put_contents($pathname, 'mp3-content');

        return $pathname;
    }

    private function createTrack(?string $pathname = null, ?float $bpm = 120.0, ?string $initialKey = '8A'): Track
    {
        $pathname = $pathname ?: $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist.mp3';

        $track = new Track(
            id: null,
            guid: self::TRACK_GUID,
            artist: 'Artist',
            artists: [ 'Artist' ],
            title: 'Track',
            album: 'Release',
            trackNumber: 1,
            year: 2020,
            genre: 'House',
            publisher: null,
            bpm: $bpm,
            initialKey: $initialKey,
            length: 180,
            tags: [],
            isFavorite: false,
            metadataMd5: 'md5',
            parent: dirname($pathname),
            pathname: $pathname,
            modifiedDate: new DateTime(),
            indexedDate: new DateTime(),
        );

        return $track;
    }

    private function createMetadata(): MusicClassifierMetadataDto
    {
        $musicClassifierMetadata = new MusicClassifierMetadataDto(
            self::TRACK_GUID,
            'md5-encoded',
            new UTCDateTime(),
            120.0,
            'A minor',
            [],
            [],
        );

        return $musicClassifierMetadata;
    }

    private function createClassifierResult(): MusicClassifierResult
    {
        $result = MusicClassifierResult::fromApiResponse([
            'bpm' => [ 'value' => 120.0, 'confidence' => 0.9 ],
            'key' => [ 'value' => 'A minor', 'confidence' => 0.8 ],
            'audio_md5' => 'md5-encoded',
            'genre' => [
                [ 'genre' => 'House', 'confidence' => 0.9 ],
            ],
            'tags' => [
                [ 'label' => 'party', 'type' => 'mood', 'confidence' => 0.8 ],
            ],
        ]);

        return $result;
    }
}
