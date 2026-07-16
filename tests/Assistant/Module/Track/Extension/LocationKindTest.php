<?php

namespace Assistant\Module\Track\Extension;

use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class LocationKindTest extends TestCase
{
    public function testSinglesUsesNestedFormat(): void
    {
        self::assertSame(
            '%artist%/%album%/%artist% - %track_number% - %title%',
            LocationKind::SINGLES->filenameFormat(),
        );
    }

    /** @dataProvider dataFlatFormatKinds */
    public function testOtherKindsUseFlatFormat(LocationKind $kind): void
    {
        self::assertSame('%artist% - %title%', $kind->filenameFormat());
    }

    public function dataFlatFormatKinds(): array
    {
        return [
            [ LocationKind::OTHER ],
            [ LocationKind::INCOMING ],
            [ LocationKind::UNSUPPORTED ],
        ];
    }

    public function testSinglesBaseDirIsTwoLevelsAboveFile(): void
    {
        $file = new SplFileInfo('/collection/Singles/A/Artist/Album/Artist - Title.mp3');

        self::assertSame('/collection/Singles/A', LocationKind::SINGLES->baseDir($file));
    }

    public function testOtherBaseDirIsFileDirectory(): void
    {
        $file = new SplFileInfo('/collection/Other/Artist - Title.mp3');

        self::assertSame('/collection/Other', LocationKind::OTHER->baseDir($file));
    }
}
