<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\SlugifyService;
use SplFileInfo;
use Symfony\Component\Process\Process;

final class MusicClassifierService
{
    /**
     * Uwaga, plik wykonywalny znajduje się w innym kontenerze.
     *
     * @see .docker/php-fpm/Dockerfile
     * @see .docker/php-fpm/bin/essentia_streaming_extractor_music
     * @link https://essentia.upf.edu/streaming_extractor_music.html
     */
    private const EXTRACTOR_BINARY = 'essentia_streaming_extractor_music';

    /**
     * Konfiguracja essentia_streaming_extractor_music. Uwaga, konfiguracja znajduje się w innym kontenerze.
     *
     * @link https://essentia.upf.edu/streaming_extractor_music.html#configuration
     */
    private const EXTRACTOR_CONFIGURATION_PROFILE = '/essentia/profile.yaml';

    public function __construct(
        private readonly Config $config,
        private readonly MusicClassifierAudioMd5Calculator $audioMd5Calculator,
        private readonly SlugifyService $slugify,
    ) {
    }

    public function analyze(SplFileInfo $track): MusicClassifierResult
    {
        $resultFile = $this->findResultFile($track);

        if (!file_exists($resultFile)) {
            $process = new Process([
                self::EXTRACTOR_BINARY,
                $track->getPathname(),
                $resultFile,
                self::EXTRACTOR_CONFIGURATION_PROFILE
            ]);

            $process
                ->setIdleTimeout(null)
                ->setTimeout(null)
                ->run();

            if (!$process->isSuccessful()) {
                if (file_exists($resultFile)) {
                    // Music classifier exception:
                    // YamlOutput: error when double-checking the output file; it doesn't match the expected output

                    unlink($resultFile);
                }

                throw new MusicClassifierProcessException($process);
            }
        }

        $result = MusicClassifierResult::fromResultFile($resultFile);

        return $result;
    }

    private function findResultFile(SplFileInfo $track): ?string
    {
        $audioMd5 = $this->audioMd5Calculator->calculate($track);
        $resultPathname = $this->generateResultFilename($track, $audioMd5);

        if (file_exists($resultPathname)) {
            return $resultPathname;
        }

        $filter = static fn (SplFileInfo $node): bool => (
            str_ends_with($node->getBasename('.' . $node->getExtension()), 'md5:' . $audioMd5)
        );

        $finder = Finder::create([
            'filter' => $filter,
            'mode' => Finder::MODE_FILES_ONLY,
            'pathname' => $this->config->get('collection.metadata_dirs.music_classifier'),
        ]);

        if ($finder->count() === 0) {
            return $resultPathname;
        }

        /** @var SplFileInfo $node */
        $node = array_values(iterator_to_array($finder))[0];
        $resultFile = $node->getPathname();

        return $resultFile;
    }

    private function generateResultFilename(SplFileInfo $track, string $audioMd5): string
    {
        $basename = $this->slugify->slugify($track->getBasename('.' . $track->getExtension()));

        $filename = sprintf('basename:%s,md5:%s.json', $basename, $audioMd5);
        $pathname = $this->config->get('collection.metadata_dirs.music_classifier') . '/' . $filename;

        return $pathname;
    }
}
