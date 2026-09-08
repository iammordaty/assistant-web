<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierAudioMd5Calculator;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\MusicClassifierMetadataDto;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\MusicClassifierMetadataRepository;
use KeyTools\KeyTools;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Tester\CommandTester;

final class MusicClassifierMetadataCalculatorTaskTest extends TestCase
{
    private string $tempDir;
    private string $collectionDir;
    private string $metadataDir;

    private TestHandler $logHandler;
    private Logger $logger;
    private Config $config;

    /** @var MusicClassifierService&MockObject */
    private MusicClassifierService $musicClassifierService;

    /** @var MusicClassifierAudioMd5Calculator&MockObject */
    private MusicClassifierAudioMd5Calculator $audioMd5Calculator;

    /** @var MusicClassifierMetadataRepository&MockObject */
    private MusicClassifierMetadataRepository $musicClassifierMetadataRepository;

    /** @var TrackService&MockObject */
    private TrackService $trackService;

    private KeyTools $keyTools;

    protected function setUp(): void
    {
        $this->tempDir = sprintf('%s/assistant-test-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        $this->collectionDir = $this->tempDir . '/collection';
        $this->metadataDir = $this->tempDir . '/metadata/essentia';

        mkdir($this->collectionDir . '/Singles/2020/01. styczen/Artist/Release', 0777, true);
        mkdir($this->metadataDir . '/Singles/2020/01. styczen/Artist/Release', 0777, true);

        $this->logHandler = new TestHandler();
        $this->logger = new Logger('test', [ $this->logHandler ]);

        $this->config = new Config([
            'collection' => [
                'root_dir' => $this->collectionDir,
                'indexed_dirs' => [
                    $this->collectionDir . '/Singles',
                    $this->collectionDir . '/Other',
                ],
                'metadata_dirs' => [
                    'music_classifier' => $this->metadataDir,
                ],
            ],
        ]);

        $this->musicClassifierService = $this->createMock(MusicClassifierService::class);
        $this->audioMd5Calculator = $this->createMock(MusicClassifierAudioMd5Calculator::class);
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
        $mp3Path = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.mp3';
        file_put_contents($mp3Path, 'mp3-content');

        $jsonPath = $this->metadataDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.json';
        file_put_contents($jsonPath, '{}');

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('count')
            ->willReturn(5);

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([ '--stat' => true ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('MP3 files in collection: 1', $output);
        self::assertStringContainsString('JSON metadata files: 1', $output);
        self::assertStringContainsString('Metadata records in database: 5', $output);
    }

    public function testCleanRemovesOrphanedMetadataFiles(): void
    {
        // Utwórz plik JSON, ale bez odpowiadającego mu pliku MP3
        $orphanedJsonPath = $this->metadataDir . '/Singles/2020/01. styczen/Artist/Release/Orphaned - 01 - Track.json';
        file_put_contents($orphanedJsonPath, '{}');

        self::assertFileExists($orphanedJsonPath);

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([ '--clean' => true ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('Cleaned 1 orphaned metadata JSON file(s).', $output);
        self::assertFileDoesNotExist($orphanedJsonPath);
    }

    public function testDefaultSkipsAlreadyCalculatedFiles(): void
    {
        $mp3Path = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.mp3';
        file_put_contents($mp3Path, 'mp3-content');

        $existingResult = $this->createClassifierResult();

        $this->musicClassifierService
            ->expects(self::once())
            ->method('getResult')
            ->willReturn($existingResult);

        // Nie powinno być wywołania analyze()
        $this->musicClassifierService
            ->expects(self::never())
            ->method('analyze');

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testSkipNotCalculatedOptionSkipsFilesWithoutJson(): void
    {
        $mp3Path = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.mp3';
        file_put_contents($mp3Path, 'mp3-content');

        $this->musicClassifierService
            ->expects(self::once())
            ->method('getResult')
            ->willReturn(null);

        $this->musicClassifierService
            ->expects(self::never())
            ->method('analyze');

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([ '--skip-not-calculated' => true ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testForceOptionRecalculatesEvenWhenJsonExists(): void
    {
        $mp3Path = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.mp3';
        file_put_contents($mp3Path, 'mp3-content');

        $existingResult = $this->createClassifierResult();
        $newResult = $this->createClassifierResult();

        $this->musicClassifierService
            ->expects(self::once())
            ->method('getResult')
            ->willReturn($existingResult);

        $this->musicClassifierService
            ->expects(self::once())
            ->method('analyze')
            ->willReturn($newResult);

        $this->musicClassifierService
            ->expects(self::once())
            ->method('moveResultToIndexedLocation')
            ->with(self::isInstanceOf(SplFileInfo::class), $newResult);

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([ '--force' => true ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testUpdateIfChromaprintDiffersRecalculatesWhenMd5Differs(): void
    {
        $mp3Path = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.mp3';
        file_put_contents($mp3Path, 'mp3-content');

        $existingResult = $this->createClassifierResult('old-md5');
        $newResult = $this->createClassifierResult('new-md5');

        $this->musicClassifierService
            ->expects(self::once())
            ->method('getResult')
            ->willReturn($existingResult);

        $this->audioMd5Calculator
            ->expects(self::once())
            ->method('calculate')
            ->willReturn('new-md5');

        $this->musicClassifierService
            ->expects(self::once())
            ->method('analyze')
            ->willReturn($newResult);

        $this->musicClassifierService
            ->expects(self::once())
            ->method('moveResultToIndexedLocation')
            ->with(self::isInstanceOf(SplFileInfo::class), $newResult);

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([ '--update-if-chromaprint-differs' => true ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testSaveToDbSavesMetadataToRepository(): void
    {
        $mp3Path = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.mp3';
        file_put_contents($mp3Path, 'mp3-content');

        $existingResult = $this->createClassifierResult();

        $this->musicClassifierService
            ->expects(self::once())
            ->method('getResult')
            ->willReturn($existingResult);

        $track = new Track(
            id: null,
            guid: 'artist-01-track',
            artist: 'Artist',
            artists: ['Artist'],
            title: 'Track',
            album: 'Release',
            trackNumber: 1,
            year: 2020,
            genre: 'House',
            publisher: null,
            bpm: 120.0,
            initialKey: '8A',
            length: 180,
            tags: [],
            isFavorite: false,
            metadataMd5: 'md5',
            parent: dirname($mp3Path),
            pathname: $mp3Path,
            modifiedDate: new \DateTime(),
            indexedDate: new \DateTime(),
        );

        $this->trackService
            ->expects(self::once())
            ->method('createFromFile')
            ->with($mp3Path)
            ->willReturn($track);

        $this->musicClassifierMetadataRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (MusicClassifierMetadataDto $dto) {
                return $dto->trackGuid === 'artist-01-track';
            }));

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([ '--save-to-db' => true ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testRespectsLimitOption(): void
    {
        $mp3Path1 = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track 1.mp3';
        $mp3Path2 = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 02 - Track 2.mp3';
        file_put_contents($mp3Path1, 'mp3-content-1');
        file_put_contents($mp3Path2, 'mp3-content-2');

        $this->musicClassifierService
            ->expects(self::once())
            ->method('getResult')
            ->willReturn($this->createClassifierResult());

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([ '--limit' => 1 ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testMismatchLoggingWhenBpmOrKeyDiffers(): void
    {
        $mp3Path = $this->collectionDir . '/Singles/2020/01. styczen/Artist/Release/Artist - 01 - Track.mp3';
        file_put_contents($mp3Path, 'mp3-content');

        // Wynik klasyfikatora: 120.0 BPM, musical key: A minor (8A)
        $result = $this->createClassifierResult();

        $this->musicClassifierService
            ->expects(self::once())
            ->method('getResult')
            ->willReturn($result);

        // Utwór w bazie: 128.0 BPM, key: 11B
        $track = new Track(
            id: null,
            guid: 'artist-01-track',
            artist: 'Artist',
            artists: ['Artist'],
            title: 'Track',
            album: 'Release',
            trackNumber: 1,
            year: 2020,
            genre: 'House',
            publisher: null,
            bpm: 128.0,
            initialKey: '11B',
            length: 180,
            tags: [],
            isFavorite: false,
            metadataMd5: 'md5',
            parent: dirname($mp3Path),
            pathname: $mp3Path,
            modifiedDate: new \DateTime(),
            indexedDate: new \DateTime(),
        );

        $this->trackService
            ->expects(self::once())
            ->method('createFromFile')
            ->with($mp3Path)
            ->willReturn($track);

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([ '--save-to-db' => true ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertTrue($this->logHandler->hasInfoThatContains('Track bpm differs from calculated value'));
        self::assertTrue($this->logHandler->hasInfoThatContains('Track initial key differs from calculated value'));
    }

    public function testAbortsWhenClassifierFailsTooOften(): void
    {
        // Utwórz więcej plików niż wynosi próg, aby wymusić serię błędów essentii
        for ($i = 1; $i <= 12; $i++) {
            $mp3Path = sprintf(
                '%s/Singles/2020/01. styczen/Artist/Release/Artist - %02d - Track %d.mp3',
                $this->collectionDir,
                $i,
                $i,
            );

            file_put_contents($mp3Path, 'mp3-content-' . $i);
        }

        // Brak metadanych JSON -> task próbuje policzyć, a analyze zawsze rzuca wyjątkiem
        $this->musicClassifierService
            ->method('getResult')
            ->willReturn(null);

        $this->musicClassifierService
            ->method('analyze')
            ->willThrowException(new MusicClassifierException('essentia is down'));

        $task = $this->createTask();
        $tester = new CommandTester($task);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertTrue(
            $this->logHandler->hasCriticalThatContains('music classifier (essentia) is failing too often'),
        );
    }

    private function createTask(): MusicClassifierMetadataCalculatorTask
    {
        return new MusicClassifierMetadataCalculatorTask(
            $this->logger,
            $this->musicClassifierService,
            $this->audioMd5Calculator,
            $this->musicClassifierMetadataRepository,
            $this->trackService,
            $this->keyTools,
            $this->config,
        );
    }

    private function createClassifierResult(string $audioMd5 = 'md5-encoded'): MusicClassifierResult
    {
        $rawResult = [
            'chromaprint' => [
                'string' => [ 'fingerprint' ],
            ],
            'metadata' => [
                'audio_properties' => [
                    'md5_encoded' => $audioMd5,
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
        ];

        $tempFile = sprintf('%s/res-%s.json', $this->tempDir, bin2hex(random_bytes(4)));
        file_put_contents($tempFile, json_encode($rawResult));

        return MusicClassifierResult::fromResultFile($tempFile);
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
