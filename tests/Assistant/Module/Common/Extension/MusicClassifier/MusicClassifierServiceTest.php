<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\SlugifyService;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class MusicClassifierServiceTest extends TestCase
{
    private string $root;
    private MusicClassifierService $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/classifier-' . bin2hex(random_bytes(6));

        mkdir($this->root . '/collection/Other', recursive: true);
        mkdir($this->root . '/metadata/essentia', recursive: true);

        $this->service = new MusicClassifierService(
            new Config([
                'collection' => [
                    'root_dir' => $this->root . '/collection',
                    'metadata_dirs' => [
                        'music_classifier' => $this->root . '/metadata/essentia',
                    ],
                ],
            ]),
            new MusicClassifierAudioMd5Calculator(),
            new SlugifyService(),
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testIndexedResultPathnameMirrorsCollectionLayout(): void
    {
        $track = new SplFileInfo($this->root . '/collection/Other/Artist - Title.mp3');

        self::assertSame(
            $this->root . '/metadata/essentia/Other/Artist - Title.json',
            $this->service->getIndexedResultPathname($track),
        );
    }

    public function testIndexedResultPathnameForNestedSinglesTrack(): void
    {
        $track = new SplFileInfo(
            $this->root . '/collection/Singles/2009/08. sierpień/Deadmau5/Ghosts/Deadmau5 - 01 - Ghosts.mp3',
        );

        self::assertSame(
            $this->root . '/metadata/essentia/Singles/2009/08. sierpień/Deadmau5/Ghosts/Deadmau5 - 01 - Ghosts.json',
            $this->service->getIndexedResultPathname($track),
        );
    }

    public function testGetResultReturnsNullWhenFileIsMissing(): void
    {
        $track = new SplFileInfo($this->root . '/collection/Other/Artist - Title.mp3');

        self::assertNull($this->service->getResult($track));
    }

    public function testGetResultReadsIndexedClassifierFile(): void
    {
        $trackPath = $this->root . '/collection/Other/Artist - Title.mp3';
        $resultPath = $this->root . '/metadata/essentia/Other/Artist - Title.json';

        mkdir(dirname($resultPath), recursive: true);
        file_put_contents($resultPath, json_encode($this->minimalResult([
            'rhythm' => [ 'bpm' => 128.4 ],
            'tonal' => [
                'chords_key' => 'C',
                'chords_scale' => 'minor',
            ],
        ])));

        $result = $this->service->getResult(new SplFileInfo($trackPath));

        self::assertInstanceOf(MusicClassifierResult::class, $result);
        self::assertSame(128.4, $result->getBpm());
        self::assertSame('C minor', $result->getMusicalKey());
        self::assertSame('md5-encoded', $result->getMd5());
        self::assertSame($resultPath, $result->getFile()?->getPathname());
    }

    private function minimalResult(array $overrides = []): array
    {
        $highlevel = [
            'value' => 'yes',
            'probability' => 0.9,
        ];

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
                'genre_electronic' => $highlevel,
                'mood_aggressive' => [ 'value' => 'not_aggressive', 'probability' => 0.1 ],
                'mood_happy' => [ 'value' => 'not_happy', 'probability' => 0.1 ],
                'mood_party' => [ 'value' => 'not_party', 'probability' => 0.1 ],
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
