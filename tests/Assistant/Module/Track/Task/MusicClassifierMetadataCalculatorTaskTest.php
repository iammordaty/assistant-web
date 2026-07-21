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
use KeyTools\KeyTools;
use MongoDB\BSON\UTCDateTime;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MusicClassifierMetadataCalculatorTaskTest extends TestCase
{
    private string $tempDir;
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
        $this->tempDir = sprintf('%s/assistant-test-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        $this->collectionDir = $this->tempDir . '/collection';

        mkdir($this->collectionDir . '/Singles/2020/01. styczen/Artist/Release', 0777, true);

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
        $this->removeDirectory($this->tempDir);
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
        $mp3Path = $this->createTrackFile();

        $this->expectTrackReadFrom($mp3Path);

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('getByTrackGuid')
            ->with('artist-01-track')
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
        $mp3Path = $this->createTrackFile();

        $this->expectTrackReadFrom($mp3Path);

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
        $mp3Path = $this->createTrackFile();

        $this->expectTrackReadFrom($mp3Path);

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
        $mp3Path = $this->createTrackFile();

        $this->expectTrackReadFrom($mp3Path);

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('getByTrackGuid')
            ->willReturn(null);

        $this->musicClassifierService
            ->expects(self::once())
            ->method('analyze')
            ->willReturn($this->createClassifierResult());

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn (MusicClassifierMetadataDto $dto): bool => $dto->trackGuid === 'artist-01-track'
                    && $dto->audioMd5 === 'md5-encoded',
            ));

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testDryRunCalculatesMetadataWithoutSavingIt(): void
    {
        $mp3Path = $this->createTrackFile();

        $this->expectTrackReadFrom($mp3Path);

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
        $mp3Path = $this->createTrackFile();

        // Utwór w bazie: 128.0 BPM, tonacja 11B; wynik klasyfikatora: 120.0 BPM, A minor (8A)
        $this->expectTrackReadFrom($mp3Path, $this->createTrack($mp3Path, 128.0, '11B'));

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
        // Utwórz więcej plików niż wynosi próg, aby wymusić serię błędów klasyfikatora
        for ($i = 1; $i <= 12; $i++) {
            $this->createTrackFile(sprintf('Artist - %02d - Track %d.mp3', $i, $i));
        }

        $this->trackService
            ->method('createFromFile')
            ->willReturn($this->createTrack());

        // Brak metadanych w bazie -> zadanie próbuje policzyć, a analyze zawsze rzuca wyjątkiem
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

    private function createTrackFile(string $basename = 'Artist - 01 - Track.mp3'): string
    {
        $pathname = sprintf(
            '%s/Singles/2020/01. styczen/Artist/Release/%s',
            $this->collectionDir,
            $basename,
        );

        file_put_contents($pathname, 'mp3-content');

        return $pathname;
    }

    private function expectTrackReadFrom(string $pathname, ?Track $track = null): void
    {
        $this->trackService
            ->expects(self::once())
            ->method('createFromFile')
            ->with($pathname)
            ->willReturn($track ?? $this->createTrack($pathname));
    }

    private function createTrack(
        ?string $pathname = null,
        ?float $bpm = 120.0,
        ?string $initialKey = '8A',
    ): Track {
        $pathname ??= $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.mp3';

        return new Track(
            id: null,
            guid: 'artist-01-track',
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
            modifiedDate: new \DateTime(),
            indexedDate: new \DateTime(),
        );
    }

    private function createMetadata(string $audioMd5 = 'md5-encoded'): MusicClassifierMetadataDto
    {
        return new MusicClassifierMetadataDto(
            'artist-01-track',
            $audioMd5,
            new UTCDateTime(),
            120.0,
            'A minor',
            [],
            [],
        );
    }

    private function createClassifierResult(string $audioMd5 = 'md5-encoded'): MusicClassifierResult
    {
        return MusicClassifierResult::fromApiResponse([
            'bpm' => [ 'value' => 120.0, 'confidence' => 0.9 ],
            'key' => [ 'value' => 'A minor', 'confidence' => 0.8 ],
            'audio_md5' => $audioMd5,
            'genre' => [
                [ 'genre' => 'House', 'confidence' => 0.9 ],
            ],
            'tags' => [
                [ 'label' => 'party', 'type' => 'mood', 'confidence' => 0.8 ],
            ],
        ]);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), [ '.', '..' ]);

        foreach ($files as $file) {
            $fullPath = $path . '/' . $file;
            is_dir($fullPath) ? $this->removeDirectory($fullPath) : unlink($fullPath);
        }

        rmdir($path);
    }
}
