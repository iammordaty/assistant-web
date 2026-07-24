<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Config;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class TrackLocationArbiterTest extends TestCase
{
    private string $root;
    private TrackLocationArbiter $arbiter;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/arbiter-' . bin2hex(random_bytes(6));

        $this->arbiter = new TrackLocationArbiter(new Config([
            'collection' => [
                'root_dir' => $this->root,
                'indexed_dirs' => [ $this->root . '/Singles', $this->root . '/Other' ],
                'incoming_dir' => $this->root . '/_new',
                'ready_dir' => $this->root . '/_new/_zrobione',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testRecognizesSinglesByIndexedDir(): void
    {
        $file = $this->makeFile("/Singles/2009/08. sierpień/Deadmau5/Ghosts 'N' Stuff/Deadmau5 - 01 - Ghosts 'N' Stuff.mp3");

        self::assertSame(LocationKind::SINGLES, $this->arbiter->getLocationKind($file));
        self::assertTrue($this->arbiter->isInCollection($file));
        self::assertSame($this->root . '/Singles', $this->arbiter->getIndexedDir($file));
    }

    public function testRecognizesOtherByIndexedDir(): void
    {
        $file = $this->makeFile('/Other/Artist - Title.mp3');

        self::assertSame(LocationKind::OTHER, $this->arbiter->getLocationKind($file));
        self::assertTrue($this->arbiter->isInCollection($file));
    }

    public function testRecognizesIncoming(): void
    {
        $file = $this->makeFile('/_new/Artist - Title.mp3');

        self::assertSame(LocationKind::INCOMING, $this->arbiter->getLocationKind($file));
        self::assertTrue($this->arbiter->isInIncoming($file));
        self::assertFalse($this->arbiter->isReady($file));
        self::assertFalse($this->arbiter->isInCollection($file));
    }

    /** ready_dir zawiera się w incoming_dir - musi być rozpoznany jako READY, nie INCOMING */
    public function testRecognizesReadyAsSublocationOfIncoming(): void
    {
        $file = $this->makeFile('/_new/_zrobione/Artist - Title.mp3');

        self::assertSame(LocationKind::READY, $this->arbiter->getLocationKind($file));
        self::assertTrue($this->arbiter->isReady($file));
        self::assertTrue($this->arbiter->isInIncoming($file));
        self::assertFalse($this->arbiter->isInCollection($file));
    }

    /** F14: plik pod root_dir, ale poza indexed_dirs, NIE jest w kolekcji */
    public function testNonIndexedPathUnderRootIsNotInCollection(): void
    {
        $file = $this->makeFile('/Tools/whatever.mp3');

        self::assertSame(LocationKind::UNSUPPORTED, $this->arbiter->getLocationKind($file));
        self::assertFalse($this->arbiter->isInCollection($file));
        self::assertNull($this->arbiter->getIndexedDir($file));
    }

    private function makeFile(string $relativePath): SplFileInfo
    {
        $pathname = $this->root . $relativePath;

        @mkdir(dirname($pathname), 0775, true);
        touch($pathname);

        return new SplFileInfo($pathname);
    }
}
