<?php

namespace Assistant\Module\Track\Extension;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class DateDirectoryTest extends TestCase
{
    /** @dataProvider dataFormat */
    public function testFormat(string $date, string $expected): void
    {
        self::assertSame($expected, DateDirectory::format(new DateTimeImmutable($date)));
    }

    public function dataFormat(): array
    {
        return [
            'styczeń' => [ '2026-01-02', '2026/01. styczeń' ],
            'kwiecień' => [ '2013-04-29', '2013/04. kwiecień' ],
            'sierpień' => [ '2009-08-13', '2009/08. sierpień' ],
            'wrzesień' => [ '2026-09-08', '2026/09. wrzesień' ],
            'grudzień' => [ '2026-12-31', '2026/12. grudzień' ],
        ];
    }

    /** @dataProvider dataTryParse */
    public function testTryParse(?string $input, ?string $expected): void
    {
        self::assertSame($expected, DateDirectory::tryParse($input));
    }

    public function dataTryParse(): array
    {
        return [
            'poprawny segment' => [ '2026/08. sierpień', '2026/08. sierpień' ],
            'obcięte ukośniki' => [ '/2026/08. sierpień/', '2026/08. sierpień' ],
            'brak wartości' => [ null, null ],
            'sam rok' => [ '2026', null ],
            'miesiąc bez zera wiodącego' => [ '2026/8. sierpień', null ],
            'próba wyjścia w górę drzewa' => [ '../../etc', null ],
            'doklejone wyjście w górę' => [ '2026/08. sierpień/../..', null ],
        ];
    }

    /** Kaskada: bierzemy wcześniejszy z czasów pliku, bo ctime bywa późniejszy niż mtime */
    public function testUsesEarlierOfFileTimestamps(): void
    {
        $pathname = sys_get_temp_dir() . '/date-dir-' . bin2hex(random_bytes(6)) . '.mp3';
        $timestamp = (new DateTimeImmutable('2013-04-29 12:00:00'))->getTimestamp();

        touch($pathname, $timestamp, $timestamp);

        try {
            self::assertSame('2013/04. kwiecień', DateDirectory::forFile(new SplFileInfo($pathname)));
        } finally {
            unlink($pathname);
        }
    }
}
