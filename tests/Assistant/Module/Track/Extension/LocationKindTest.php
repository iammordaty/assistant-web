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
            [ LocationKind::READY ],
            [ LocationKind::UNSUPPORTED ],
        ];
    }

    public function testSinglesBaseDirIsTwoLevelsAboveFile(): void
    {
        // realna struktura: /collection/Singles/<Rok>/<Miesiąc>/<Artist>/<Release>/plik
        // baza = dwa poziomy nad plikiem (Rok/Miesiąc), format sam odbuduje Artist/Release z metadanych
        $file = new SplFileInfo(
            "/collection/Singles/2009/08. sierpień/Deadmau5/Ghosts 'N' Stuff/Deadmau5 - 01 - Ghosts 'N' Stuff.mp3"
        );

        self::assertSame('/collection/Singles/2009/08. sierpień', LocationKind::SINGLES->baseDir($file));
    }

    public function testOtherBaseDirIsFileDirectory(): void
    {
        $file = new SplFileInfo('/collection/Other/Artist - Title.mp3');

        self::assertSame('/collection/Other', LocationKind::OTHER->baseDir($file));
    }
}
