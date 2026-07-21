<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Track\Model\IncomingTrack;
use PHPUnit\Framework\TestCase;

final class FilenameFormatSuggesterTest extends TestCase
{
    private string $root;
    private FilenameFormatSuggester $suggester;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/suggester-' . bin2hex(random_bytes(6));

        $arbiter = new TrackLocationArbiter(new Config([
            'collection' => [
                'root_dir' => $this->root,
                'indexed_dirs' => [ $this->root . '/Singles', $this->root . '/Other' ],
                'incoming_dir' => $this->root . '/_new',
                'ready_dir' => $this->root . '/_new/_zrobione',
            ],
        ]));

        $this->suggester = new FilenameFormatSuggester($arbiter);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /** Wydanie jednego artysty - numer w środku, katalogi odbudowywane z metadanych */
    public function testSuggestsSingleArtistReleaseForNumberInTheMiddle(): void
    {
        $track = $this->makeTrack(
            '/Singles/2009/08. sierpień/Hardy Hard/Hardy Hard presents The Silver Surfer 2003/'
            . 'Hardy Hard - 01 - The Silver Surfer 2003 [Original].mp3'
        );

        self::assertSame(FilenameFormat::SINGLE_ARTIST_RELEASE, $this->suggester->suggest($track));
    }

    /** Wydanie z różnymi artystami - numer z przodu zachowuje kolejność utworów */
    public function testSuggestsMultiArtistReleaseForLeadingNumber(): void
    {
        $track = $this->makeTrack(
            '/Singles/2013/04. kwiecień/David Guetta vs. The Egg/Love Don\'t Let Me Go (Walking Away)/'
            . '03. The Egg - Walking Away [Tocadisco\'s Acid Walk Mix].mp3'
        );

        self::assertSame(FilenameFormat::MULTI_ARTIST_RELEASE, $this->suggester->suggest($track));
    }

    public function testSuggestsArtistTitleForOther(): void
    {
        $track = $this->makeTrack('/Other/2009/08. sierpień/Artist - Title.mp3');

        self::assertSame(FilenameFormat::ARTIST_TITLE, $this->suggester->suggest($track));
    }

    /** Poza kolekcją układ Singles nie ma zastosowania, nawet gdy nazwa zaczyna się od numeru */
    public function testSuggestsArtistTitleForIncomingWithLeadingNumber(): void
    {
        $track = $this->makeTrack('/_new/01. Artist - Title.mp3');

        self::assertSame(FilenameFormat::ARTIST_TITLE, $this->suggester->suggest($track));
    }

    private function makeTrack(string $relativePath): IncomingTrack
    {
        $pathname = $this->root . $relativePath;

        @mkdir(dirname($pathname), 0775, true);
        touch($pathname);

        // podpowiedź opiera się wyłącznie na lokalizacji i nazwie pliku, więc metadane są nieistotne
        return new IncomingTrack(
            guid: 'guid',
            artist: 'Artist',
            artists: [ 'Artist' ],
            title: 'Title',
            album: 'Album',
            trackNumber: 1,
            year: 2009,
            genre: null,
            publisher: null,
            bpm: null,
            initialKey: null,
            length: 0,
            tags: [],
            pathname: $pathname,
        );
    }
}
