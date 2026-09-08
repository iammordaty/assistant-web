<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Breadcrumbs\Breadcrumbs;
use Assistant\Module\Common\Extension\Breadcrumbs\BreadcrumbsBuilder;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Track\Model\IncomingTrack;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Liczenie ścieżki docelowej bez efektów ubocznych. Sedno: Singles/Other, rok i miesiąc są
 * nienaruszalne, a to, ile katalogów format odbudowuje, wynika z samego formatu.
 */
final class TrackRenameServiceTargetTest extends TestCase
{
    private string $root;
    private TrackRenameService $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rename-target-' . bin2hex(random_bytes(6));

        $config = new Config([
            'collection' => [
                'root_dir' => $this->root,
                'indexed_dirs' => [ $this->root . '/Singles', $this->root . '/Other' ],
                'incoming_dir' => $this->root . '/_new',
                'ready_dir' => $this->root . '/_new/_zrobione',
            ],
        ]);

        $logger = new Logger('test', [ new NullHandler() ]);

        $this->service = new TrackRenameService(
            new BreadcrumbsBuilder(new Breadcrumbs(new SlugifyService())),
            $config,
            $logger,
            new TrackFilenameSuggestion(),
            new TrackLocationArbiter($config),
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /** Format z dwoma ukośnikami odbudowuje artystę i album pod katalogiem miesiąca */
    public function testSingleArtistReleaseRebuildsDirectoriesUnderMonth(): void
    {
        $track = $this->makeTrack(
            '/Singles/2009/08. sierpień/Hardy Hard/Silver Surfer/Hardy Hard - 01 - Silver Surfer.mp3'
        );

        $target = $this->service->resolveTarget(
            $track,
            FilenameFormat::SINGLE_ARTIST_RELEASE->value,
            $this->metadata([ 'artist' => 'Hardy Hard', 'album' => 'Silver Surfer 2003', 'title' => 'Original' ]),
            markAsReady: false,
        );

        self::assertSame(
            $this->root . '/Singles/2009/08. sierpień/Hardy Hard/Silver Surfer 2003/Hardy Hard - 01 - Original.mp3',
            $target->getPathname(),
        );
    }

    /** Format bez ukośników zostawia katalog wydania nietknięty - tak działa układ z numerem z przodu */
    public function testMultiArtistReleaseKeepsReleaseDirectory(): void
    {
        $track = $this->makeTrack(
            '/Singles/2013/04. kwiecień/David Guetta vs. The Egg/Love/03. The Egg - Walking Away.mp3'
        );

        $target = $this->service->resolveTarget(
            $track,
            FilenameFormat::MULTI_ARTIST_RELEASE->value,
            $this->metadata([ 'artist' => 'The Egg', 'title' => 'Walking Away (Acid Walk Mix)', 'track_number' => 3 ]),
            markAsReady: false,
        );

        self::assertSame(
            $this->root . '/Singles/2013/04. kwiecień/David Guetta vs. The Egg/Love/'
            . '03. The Egg - Walking Away (Acid Walk Mix).mp3',
            $target->getPathname(),
        );
    }

    public function testArtistTitleKeepsOtherTrackInItsMonthDirectory(): void
    {
        $track = $this->makeTrack('/Other/2009/08. sierpień/Artist - Title.mp3');

        $target = $this->service->resolveTarget(
            $track,
            FilenameFormat::ARTIST_TITLE->value,
            $this->metadata([ 'artist' => 'Artist', 'title' => 'Better Title' ]),
            markAsReady: false,
        );

        self::assertSame($this->root . '/Other/2009/08. sierpień/Artist - Better Title.mp3', $target->getPathname());
    }

    /** Format wybrany wbrew podpowiedzi nie może wynieść pliku ponad katalog miesiąca */
    public function testNestedFormatOnOtherTrackStopsAtMonthDirectory(): void
    {
        $track = $this->makeTrack('/Other/2009/08. sierpień/Artist - Title.mp3');

        $target = $this->service->resolveTarget(
            $track,
            FilenameFormat::SINGLE_ARTIST_RELEASE->value,
            $this->metadata([ 'artist' => 'Artist', 'album' => 'Album', 'title' => 'Title' ]),
            markAsReady: false,
        );

        self::assertSame(
            $this->root . '/Other/2009/08. sierpień/Artist/Album/Artist - 01 - Title.mp3',
            $target->getPathname(),
        );
    }

    /** Nazwa wpisana ręcznie jest względna wobec niezmiennej części ścieżki - tak, jak w podglądzie */
    public function testManualTargetIsRelativeToFixedBaseDir(): void
    {
        $track = $this->makeTrack(
            '/Singles/2013/04. kwiecień/David Guetta vs. The Egg/Love/03. The Egg - Walking Away.mp3'
        );

        self::assertSame(
            $this->root . '/Singles/2013/04. kwiecień',
            $this->service->getFixedBaseDir($track),
        );

        $target = $this->service->resolveManualTarget($track, 'Inny katalog/Inne wydanie/cokolwiek.mp3');

        self::assertSame(
            $this->root . '/Singles/2013/04. kwiecień/Inny katalog/Inne wydanie/cokolwiek.mp3',
            $target->getPathname(),
        );
    }

    /** Nawet nazwa z próbą wyjścia w górę zostaje pod katalogiem miesiąca */
    public function testManualTargetCannotEscapeAboveMonthDirectory(): void
    {
        $track = $this->makeTrack('/Other/2009/08. sierpień/Artist - Title.mp3');

        $target = $this->service->resolveManualTarget($track, '/Artist - Title.mp3');

        self::assertStringStartsWith($this->root . '/Other/2009/08. sierpień/', $target->getPathname());
    }

    /** W incoming granicą jest katalog incoming, a "oznacz jako gotowy" dokłada podkatalog _zrobione */
    public function testMarkAsReadyPrependsReadyDirectory(): void
    {
        $track = $this->makeTrack('/_new/cokolwiek.mp3');

        $target = $this->service->resolveTarget(
            $track,
            FilenameFormat::ARTIST_TITLE->value,
            $this->metadata([ 'artist' => 'Artist', 'title' => 'Title' ]),
            markAsReady: true,
        );

        self::assertSame($this->root . '/_new/_zrobione/Artist - Title.mp3', $target->getPathname());
    }

    private function metadata(array $overrides): array
    {
        return $overrides + [ 'artist' => 'Artist', 'title' => 'Title', 'album' => 'Album', 'track_number' => 1 ];
    }

    private function makeTrack(string $relativePath): IncomingTrack
    {
        $pathname = $this->root . $relativePath;

        @mkdir(dirname($pathname), 0775, true);
        touch($pathname);

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
