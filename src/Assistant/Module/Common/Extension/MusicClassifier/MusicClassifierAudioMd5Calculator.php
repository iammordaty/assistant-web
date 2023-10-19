<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use SplFileInfo;
use Symfony\Component\Process\Process;

final class MusicClassifierAudioMd5Calculator
{
    /**
     * Uwaga, plik wykonywalny znajduje się w innym kontenerze.
     *
     * @see .docker/php-fpm/Dockerfile
     * @see .docker/php-fpm/bin/essentia_streaming_md5
     */
    private const CALCULATOR_BINARY = 'essentia_streaming_md5';

    public function calculate(SplFileInfo $track): string
    {
        $process = new Process([
            self::CALCULATOR_BINARY,
            $track->getPathname(),
        ]);

        $process
            ->setIdleTimeout(null)
            ->setTimeout(null)
            ->run();

        if (!$process->isSuccessful()) {
            throw new MusicClassifierProcessException($process);
        }

        // Przykładowy output:
        // MD5 extractor computes MD5 value (...) metadata can differ.\n
        // MD5: 7806cbe0450447d33d9e0eb570cd3069\n

        $output = explode(PHP_EOL, $process->getOutput())[1];
        $md5 = substr($output, 5);

        return $md5;
    }
}
