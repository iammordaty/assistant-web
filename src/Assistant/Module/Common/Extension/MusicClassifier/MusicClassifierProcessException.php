<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use Symfony\Component\Process\Process;

final class MusicClassifierProcessException extends MusicClassifierException
{
    public function __construct(private Process $process)
    {
        $errorMessage = self::getProbableErrorMessage($process->getErrorOutput());

        $message = sprintf(
            'Music classifier process failed with message: %s (exit code: %d)',
            $errorMessage,
            $process->getExitCode(),
        );

        parent::__construct($message);
    }

    public function getProcessCommandLine(): string
    {
        return $this->process->getCommandLine();
    }

    private static function getProbableErrorMessage(string $rawOutput): string
    {
        // odfiltruj wiersze typu "[   INFO   ] MusicExtractorSVM: adding SVM model <path-to-model>"
        $errorOutputWithoutInfo = array_filter(
            explode(PHP_EOL, trim($rawOutput)),
            fn($line) => !str_contains($line, '[   INFO   ]')
        );

        return implode(', ', $errorOutputWithoutInfo);
    }
}
