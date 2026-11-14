<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class MusicClassifierServiceTest extends TestCase
{
    private const string TRACK_PATHNAME = '/collection/Singles/2026/01. styczeń/Artist/Release/Artist - 01 - Title.mp3';

    private MockHandler $mockHandler;
    private MusicClassifierService $service;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();

        $client = new Client([
            'base_uri' => 'http://music-classifier/',
            'handler' => HandlerStack::create($this->mockHandler),
        ]);

        $this->service = new MusicClassifierService($client);
    }

    public function testAnalyzeMapsApiResponse(): void
    {
        $this->mockHandler->append(new Response(200, body: json_encode([
            'audio_md5' => '0ea476e3977df9c064003ab22927828e',
            'bpm' => [ 'value' => 135.97, 'confidence' => 2.7273 ],
            'key' => [ 'value' => 'F# minor', 'confidence' => 0.6188 ],
            'genre' => [],
            'tags' => [],
        ])));

        $result = $this->service->analyze(new SplFileInfo(self::TRACK_PATHNAME));

        self::assertSame('0ea476e3977df9c064003ab22927828e', $result->getAudioMd5());
        self::assertSame(136.0, $result->getBpm());
        self::assertSame('F# minor', $result->getMusicalKey());
    }

    public function testAnalyzeRequestsTrackPathname(): void
    {
        $this->mockHandler->append(function (Request $request): Response {
            self::assertSame('/process', $request->getUri()->getPath());
            self::assertSame('pathname=' . rawurlencode(self::TRACK_PATHNAME), $request->getUri()->getQuery());

            return new Response(200, body: json_encode([
                'audio_md5' => 'md5',
                'bpm' => [ 'value' => 128.0, 'confidence' => 3.0 ],
                'key' => [ 'value' => 'C minor', 'confidence' => 0.8 ],
                'genre' => [],
                'tags' => [],
            ]));
        });

        $this->service->analyze(new SplFileInfo(self::TRACK_PATHNAME));
    }

    public function testAnalyzerErrorRejectsWholeResult(): void
    {
        $this->mockHandler->append(new Response(200, body: json_encode([
            'audio_md5' => 'md5',
            'bpm' => [ 'value' => 128.0, 'confidence' => 3.0 ],
            'key' => null,
            'genre' => [],
            'tags' => [],
            'errors' => [
                [ 'analyzer' => 'key', 'message' => 'Essentia exception' ],
            ],
        ])));

        $this->expectException(MusicClassifierException::class);
        $this->expectExceptionMessage('Music classifier failed: key');

        $this->service->analyze(new SplFileInfo(self::TRACK_PATHNAME));
    }

    public function testApiErrorIsReportedAsMusicClassifierException(): void
    {
        $this->mockHandler->append(new Response(404, body: json_encode([
            'error' => [ 'message' => 'File not found: ' . self::TRACK_PATHNAME ],
        ])));

        $this->expectException(MusicClassifierException::class);

        $this->service->analyze(new SplFileInfo(self::TRACK_PATHNAME));
    }

    public function testMalformedResponseIsReportedAsMusicClassifierException(): void
    {
        $this->mockHandler->append(new Response(200, body: 'Internal Server Error'));

        $this->expectException(MusicClassifierException::class);

        $this->service->analyze(new SplFileInfo(self::TRACK_PATHNAME));
    }
}
