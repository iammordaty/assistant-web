<?php

namespace Assistant\Module\Track\Extension;

use PHPUnit\Framework\TestCase;

final class TrackRenameServiceTest extends TestCase
{
    /** @dataProvider dataSanitize */
    public function testSanitizeForFilesystem(string $input, string $expected): void
    {
        self::assertSame($expected, TrackRenameService::sanitizeForFilesystem($input));
    }

    public function dataSanitize(): array
    {
        return [
            'slash and colon become dash' => [ 'AC/DC', 'AC-DC' ],
            'colon and backslash' => [ 'P:\\stuff', 'P-stuff' ],
            'double quote becomes single' => [ '"quoted"', "'quoted'" ],
            'windows-forbidden chars removed' => [ 'a<b>c|d*e?f', 'abcdef' ],
            'control chars removed' => [ "a\x00b\x1Fc\x7Fd", 'abcd' ],
            'multiple spaces collapsed' => [ 'a    b', 'a b' ],
            'trailing dots and spaces trimmed' => [ '  name.  ', 'name' ],
            'percent is kept' => [ '100%', '100%' ],
            'reserved name is prefixed' => [ 'CON', '_CON' ],
            'reserved name case-insensitive' => [ 'nul', '_nul' ],
            'empty result gets fallback' => [ '***', '_' ],
        ];
    }

    public function testLongValueIsTruncatedTo255(): void
    {
        $value = str_repeat('a', 300);

        self::assertSame(255, mb_strlen(TrackRenameService::sanitizeForFilesystem($value)));
    }

    /** B2/F13: kasuje w górę od katalogu źródła (kaskadą) aż do granicy (wyłącznie) */
    public function testRemoveEmptyDirectoriesCascadesUpToBoundary(): void
    {
        $root = sys_get_temp_dir() . '/leftover-' . bin2hex(random_bytes(6));
        $boundary = $root . '/Singles';
        $deepest = $boundary . '/A/Artist/Album';

        mkdir($deepest, 0775, true);

        try {
            // wszystkie katalogi puste - kasujemy Album, Artist, A; granicy (Singles) nie ruszamy
            $removed = $this->invokeRemoveEmptyDirectoriesUpTo($deepest, $boundary);

            self::assertSame(
                [ $deepest, $boundary . '/A/Artist', $boundary . '/A' ],
                $removed,
            );
            self::assertDirectoryDoesNotExist($boundary . '/A');
            self::assertDirectoryExists($boundary); // granica nietknięta
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    public function testRemoveEmptyDirectoriesStopsAtNonEmptyDirectory(): void
    {
        $root = sys_get_temp_dir() . '/leftover-' . bin2hex(random_bytes(6));
        $boundary = $root . '/Singles';
        $deepest = $boundary . '/A/Artist/Album';

        mkdir($deepest, 0775, true);
        touch($boundary . '/A/Artist/keep.mp3'); // Artist nie jest pusty

        try {
            $removed = $this->invokeRemoveEmptyDirectoriesUpTo($deepest, $boundary);

            self::assertSame([ $deepest ], $removed);
            self::assertDirectoryExists($boundary . '/A/Artist');
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    /** B5: katalog z samymi dotfile'ami jest uznany za pusty */
    public function testDirectoryWithOnlyDotfilesIsEmpty(): void
    {
        $root = sys_get_temp_dir() . '/empty-' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);
        touch($root . '/.DS_Store');

        try {
            self::assertTrue($this->invokeIsDirectoryEmpty($root));
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    public function testMissingDirectoryIsNotEmpty(): void
    {
        self::assertFalse($this->invokeIsDirectoryEmpty('/no/such/dir/here'));
    }

    private function invokeRemoveEmptyDirectoriesUpTo(string $startDir, string $boundary): array
    {
        $method = new \ReflectionMethod(TrackRenameService::class, 'removeEmptyDirectoriesUpTo');

        return $method->invoke(null, $startDir, $boundary);
    }

    private function invokeIsDirectoryEmpty(string $path): bool
    {
        $method = new \ReflectionMethod(TrackRenameService::class, 'isDirectoryEmpty');

        return $method->invoke(null, $path);
    }
}
