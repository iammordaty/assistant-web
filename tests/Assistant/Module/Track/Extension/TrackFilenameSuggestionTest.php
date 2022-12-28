<?php

namespace Assistant\Module\Track\Extension;

use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class TrackFilenameSuggestionTest extends TestCase
{
    /** @dataProvider dataFilenames */
    public function testGetSuggestedFilename(string $pathname, string $expectedSuggestion)
    {
        $tfs = new TrackFilenameSuggestion();
        $suggestion = $tfs->getSuggestedFilename(new SplFileInfo($pathname));

        $this->assertEquals($expectedSuggestion, $suggestion);
    }

    public function dataFilenames(): array
    {
        return [
            [
                'lorem-ipsum_-_dolor-sit-amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet.MP3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'lorem_ipsum_-_dolor_sit_amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '01 --- lorem_ipsum_-_dolor_sit_amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'lorem ipsum - dolor sit amet (dons eus).mp3',
                'Lorem Ipsum - Dolor Sit Amet (Dons Eus).mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet_(dons_eus).mp3',
                'Lorem Ipsum - Dolor Sit Amet (Dons Eus).mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet_(dons_eus)-dus.mp3',
                'Lorem Ipsum - Dolor Sit Amet (Dons Eus).mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet_(dons_eus)_[1LOREM2].mp3',
                'Lorem Ipsum - Dolor Sit Amet (Dons Eus).mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet_(dons_eus)_[LOREM].mp3',
                'Lorem Ipsum - Dolor Sit Amet (Dons Eus).mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet_(dons_eus)[LOREM].mp3',
                'Lorem Ipsum - Dolor Sit Amet (Dons Eus).mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet_(dons_eus)_[LOREM_IPSUM].mp3',
                'Lorem Ipsum - Dolor Sit Amet (Dons Eus).mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet-domain.com.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet-www.domain.com.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'lorem-ipsum_-_dolor-sit-amet-ftp.domain.com.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'Lorem Ipsum - Dolor Sit Amet [domain.com].mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '01. lorem-ipsum_-_dolor-sit-amet-www.domain.tld.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '01 - lorem-ipsum_-_dolor-sit-amet-www.domain.tld.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '(01) lorem-ipsum_-_dolor-sit-amet-www.domain.tld.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '01 lorem-ipsum_-_dolor-sit-amet-www.domain.tld.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '01 lorem-ipsum_-_dolor-sit-amet-www.domain.tld.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '--- 1.5 lorem-ipsum_-_dolor-sit-amet-www.domain.tld.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                // na taką sytuację jest specjalny regexp (usuń wielokrotne - -),
                // co jest słabe i najlepiej byłoby to ograć przez powyższe kroki
                'Lorem Ipsum - 01 - Dolor Sit Amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '1A lorem-ipsum_-_dolor-sit-amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'Abm lorem-ipsum_-_dolor-sit-amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'G#m lorem-ipsum_-_dolor-sit-amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                'g#m lorem-ipsum_-_dolor-sit-amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '125 - 12A - lorem-ipsum_-_dolor-sit-amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '01A - 125 - lorem-ipsum_-_dolor-sit-amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
            [
                '01a - 125 - lorem-ipsum_-_dolor-sit-amet.mp3',
                'Lorem Ipsum - Dolor Sit Amet.mp3',
            ],
        ];
    }
}
