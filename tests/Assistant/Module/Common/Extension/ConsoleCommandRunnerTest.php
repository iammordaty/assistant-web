<?php

namespace Assistant\Module\Common\Extension;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class ConsoleCommandRunnerTest extends TestCase
{
    private ConsoleCommandRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new ConsoleCommandRunner(
            new Logger('test'),
            new Config([ 'base_dir' => '/data' ]),
        );
    }

    public function testBuildsConsoleCommandLineWithConsolePath(): void
    {
        $line = $this->runner->buildConsoleCommandLine([ 'track:calculate-audio-data', '-w', '/collection/Artist - Title.mp3' ]);

        self::assertSame(
            "'php' '/data/bin/console.php' 'track:calculate-audio-data' '-w' '/collection/Artist - Title.mp3'",
            $line,
        );
    }

    /** B11: niebezpieczne konstrukcje w ścieżce są zneutralizowane przez escapeshellarg */
    public function testDangerousPathIsEscaped(): void
    {
        $line = $this->runner->buildConsoleCommandLine([ 'track:calculate-audio-data', '-w', '/x/$(reboot).mp3' ]);

        // pojedyncze cudzysłowy sprawiają, że $(...) nie jest interpretowane przez powłokę
        self::assertStringContainsString("'/x/\$(reboot).mp3'", $line);
        self::assertStringNotContainsString('$(reboot)"', $line);
    }
}
