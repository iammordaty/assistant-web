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

    /** @dataProvider dataTryParseYear */
    public function testTryParseYear(?string $input, ?string $expected): void
    {
        self::assertSame($expected, DateDirectory::tryParseYear($input));
    }

    public function dataTryParseYear(): array
    {
        return [
            'poprawny rok' => [ '2026', '2026' ],
            'brak wartości' => [ null, null ],
            'pusty' => [ '  ', null ],
            'za krótki' => [ '26', null ],
            'próba wyjścia w górę drzewa' => [ '../..', null ],
        ];
    }

    /** @dataProvider dataTryParseMonth */
    public function testTryParseMonth(?string $input, ?string $expected): void
    {
        self::assertSame($expected, DateDirectory::tryParseMonth($input));
    }

    public function dataTryParseMonth(): array
    {
        return [
            'poprawny miesiąc' => [ '08. sierpień', '08. sierpień' ],
            'brak wartości' => [ null, null ],
            'bez zera wiodącego' => [ '8. sierpień', null ],
            'z ukośnikiem' => [ '08. sierpień/..', null ],
            'próba wyjścia w górę drzewa' => [ '../etc', null ],
        ];
    }

    /** Rok i miesiąc są niezależne - pominięty bierze się z daty pliku */
    public function testYearAndMonthAreIndependentOverrides(): void
    {
        $pathname = sys_get_temp_dir() . '/date-dir-' . bin2hex(random_bytes(6)) . '.mp3';
        $timestamp = (new DateTimeImmutable('2013-04-29 12:00:00'))->getTimestamp();

        touch($pathname, $timestamp, $timestamp);
        $file = new SplFileInfo($pathname);

        try {
            self::assertSame('2013/04. kwiecień', DateDirectory::forFile($file));
            self::assertSame('2026/04. kwiecień', DateDirectory::forFile($file, '2026', null));
            self::assertSame('2013/09. wrzesień', DateDirectory::forFile($file, null, '09. wrzesień'));
            self::assertSame('2026/09. wrzesień', DateDirectory::forFile($file, '2026', '09. wrzesień'));
        } finally {
            unlink($pathname);
        }
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
