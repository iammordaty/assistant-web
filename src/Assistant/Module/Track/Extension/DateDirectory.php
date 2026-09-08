<?php

namespace Assistant\Module\Track\Extension;

use DateTimeImmutable;
use IntlDateFormatter;
use SplFileInfo;

/**
 * Buduje segment ścieżki "<rok>/<NN. miesiąc>", np. "2026/08. sierpień" - taki, jaki obowiązuje
 * w kolekcji. Używany przy porządkowaniu katalogu incoming; kolekcja własnego segmentu nigdy nie
 * przelicza, bo rok i miesiąc są tam nienaruszalne.
 *
 * Data pochodzi z kaskady: czas utworzenia pliku -> czas modyfikacji -> data bieżąca, przy czym
 * wartość podana wprost przez użytkownika wygrywa ze wszystkim.
 *
 * Uwaga: PHP na Linuksie nie udostępnia czasu utworzenia pliku - filectime() zwraca czas zmiany
 * i-węzła, który bywa PÓŹNIEJSZY niż czas modyfikacji (np. po zmianie uprawnień czy tagów).
 * Bierzemy więc wcześniejszy z obu czasów, co najlepiej przybliża moment pojawienia się pliku.
 */
final class DateDirectory
{
    private const string MONTH_NAME_PATTERN = 'LLLL';

    public static function forFile(SplFileInfo $file): string
    {
        return self::format(self::resolveDate($file));
    }

    /**
     * Przyjmuje segment podany wprost (np. z formularza) tylko wtedy, gdy ma dokładnie kształt
     * "RRRR/NN. miesiąc". Wartość trafia do formatu nazwy pliku, więc cokolwiek innego - w tym
     * próba wyjścia w górę drzewa - musi zostać odrzucone.
     */
    public static function tryParse(?string $value): ?string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B/");

        return preg_match('/^\d{4}\/\d{2}\. \p{L}+$/u', $value) === 1 ? $value : null;
    }

    public static function format(DateTimeImmutable $date): string
    {
        return sprintf('%s/%s. %s', $date->format('Y'), $date->format('m'), self::monthName($date));
    }

    private static function resolveDate(SplFileInfo $file): DateTimeImmutable
    {
        $timestamps = array_filter([ @$file->getCTime(), @$file->getMTime() ]);

        if (!$timestamps) {
            return new DateTimeImmutable();
        }

        return (new DateTimeImmutable())->setTimestamp(min($timestamps));
    }

    /** AI: duplikat konfiguracji IntlDateFormatter z RecentTracksController - świadomy, do scalenia przy refaktorze */
    private static function monthName(DateTimeImmutable $date): string
    {
        $formatter = new IntlDateFormatter(
            locale: 'pl_PL',
            dateType: IntlDateFormatter::LONG,
            timeType: IntlDateFormatter::NONE,
            pattern: self::MONTH_NAME_PATTERN,
        );

        return mb_strtolower($formatter->format($date));
    }
}
