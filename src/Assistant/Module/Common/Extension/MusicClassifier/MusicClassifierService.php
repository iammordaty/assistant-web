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
     * Nazwa essentia_streaming_extractor_music. Uwaga, plik wykonywalny znajduje się w innym kontenerze.
     *
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
        private Config $config,
        private SlugifyService $slugify,
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
                throw new MusicClassifierException($process);
            }
        }

        $result = MusicClassifierResult::fromOutputJsonFile($resultFile);

        return $result;
    }

    private function findResultFile(SplFileInfo $track): ?string
    {
        $resultPathname = $this->generateResultFilename($track);

        if (file_exists($resultPathname)) {
            return $resultPathname;
        }

        $filter = static fn(SplFileInfo $node): bool => (
            str_ends_with($node->getBasename('.' . $node->getExtension()), 'inode:' . $track->getInode())
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

    /**
     * @link https://www.linuxquestions.org/questions/linux-general-1/inode-number-questions-can-they-change-and-when-are-they-reused-592388/
     * @link https://unix.stackexchange.com/questions/192800/does-the-inode-change-when-renaming-or-moving-a-file
     */
    private function generateResultFilename(SplFileInfo $track): string
    {
        $basename = $this->slugify->slugify($track->getBasename('.' . $track->getExtension()));
        $inode = $track->getInode();

        $filename = sprintf('basename:%s,inode:%d.json', $basename, $inode);
        $pathname = $this->config->get('collection.metadata_dirs.music_classifier') . '/' . $filename;

        return $pathname;
    }
}
