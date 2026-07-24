<?php

namespace Assistant\Module\Common\Extension;

use Cocur\BackgroundProcess\BackgroundProcess;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Uruchamia komendy konsolowe.
 *
 * - runSync(): w tym samym procesie PHP, bez powłoki (brak command injection, sprawdza kod wyjścia).
 * - runAsync(): jako odłączony proces w tle (dla zadań, na które nie chcemy czekać, np. analiza audio);
 *   każdy token jest escapowany przez escapeshellarg(), więc nie ma wektora command injection (B11).
 */
final readonly class ConsoleCommandRunner
{
    public function __construct(
        private Logger $logger,
        private Config $config,
    ) {
    }

    /**
     * @param array $input argumenty i opcje w formacie ArrayInput, np. [ 'pathname' => $p, '--write-data' => true ]
     */
    public function runSync(Command $command, array $input = []): int
    {
        $arrayInput = new ArrayInput($input);
        $arrayInput->setInteractive(false);

        try {
            $exitCode = $command->run($arrayInput, new NullOutput());
        } catch (\Throwable $e) {
            $this->logger->error('Console command failed', [
                'command' => $command->getName(),
                'input' => $input,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }

        if ($exitCode !== Command::SUCCESS) {
            $this->logger->warning('Console command finished with non-zero exit code', [
                'command' => $command->getName(),
                'input' => $input,
                'exit_code' => $exitCode,
            ]);
        }

        return $exitCode;
    }

    /**
     * Uruchamia komendę konsolową jako odłączony proces w tle (fire-and-forget).
     *
     * @param string[] $command tokeny komendy, np. [ 'track:calculate-audio-data', '-w', $pathname ]
     */
    public function runAsync(array $command): void
    {
        (new BackgroundProcess($this->buildConsoleCommandLine($command)))->run();
    }

    /**
     * Składa bezpieczną linię poleceń do uruchomienia console.php. Każdy token (w tym ścieżki
     * pochodzące z metadanych) jest osobno escapowany - to zamyka wektor command injection (B11).
     *
     * @param string[] $command
     */
    public function buildConsoleCommandLine(array $command): string
    {
        $tokens = [ 'php', $this->config->get('base_dir') . '/bin/console.php', ...$command ];

        return implode(' ', array_map('escapeshellarg', $tokens));
    }
}
