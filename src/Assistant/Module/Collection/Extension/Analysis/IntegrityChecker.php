<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Track\Extension\TrackRenameService;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;

final readonly class IntegrityChecker implements CheckerInterface
{
    public function __construct(
        private TrackRepository $trackRepository,
        private SimilarTracksCollectionService $similarTracksCollectionService,
        private Config $config,
    ) {
    }

    public function getCategory(): AnalysisCategory
    {
        return AnalysisCategory::COLLECTION;
    }

    /** @return AnalysisIssue[] */
    public function check(): array
    {
        $tracks = iterator_to_array($this->trackRepository->findBy(new SearchCriteria()));

        $dbPathnames = [];

        foreach ($tracks as $track) {
            $dbPathnames[$track->getPathname()] = true;
        }

        $diskPathnames = $this->getDiskPathnames();
        $muslyPathnames = $this->getMuslyPathnames();

        return [
            ...$this->findMissingOnDisk($dbPathnames, $diskPathnames),
            ...$this->findNotInDb($dbPathnames, $diskPathnames),
            ...$this->findNotInMusly($dbPathnames, $muslyPathnames),
            ...$this->findNotInDbButInMusly($dbPathnames, $muslyPathnames),
            ...$this->checkFilenameMetadataMismatch($tracks),
        ];
    }

    private function getDiskPathnames(): array
    {
        $indexedDirs = $this->config->get('collection.indexed_dirs');
        $pathnames = [];

        foreach ($indexedDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $finder = Finder::create([
                'pathname' => $dir,
                'recursive' => true,
                'skip_self' => true,
                'mode' => Finder::MODE_FILES_ONLY,
            ]);

            foreach ($finder as $file) {
                $pathnames[$file->getPathname()] = true;
            }
        }

        return $pathnames;
    }

    private function getMuslyPathnames(): array
    {
        $tracks = $this->similarTracksCollectionService->getTracks();
        $pathnames = [];

        foreach ($tracks as $track) {
            $pathnames[$track['track-origin']] = true;
        }

        return $pathnames;
    }

    /** @return AnalysisIssue[] */
    private function findMissingOnDisk(array $dbPathnames, array $diskPathnames): array
    {
        $issues = [];

        foreach ($dbPathnames as $pathname => $_) {
            if (!isset($diskPathnames[$pathname])) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'missing_on_disk', [
                    'pathname' => $pathname,
                ]);
            }
        }

        return $issues;
    }

    /** @return AnalysisIssue[] */
    private function findNotInDb(array $dbPathnames, array $diskPathnames): array
    {
        $issues = [];

        foreach ($diskPathnames as $pathname => $_) {
            if (!isset($dbPathnames[$pathname])) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'not_in_db', [
                    'pathname' => $pathname,
                ]);
            }
        }

        return $issues;
    }

    /** @return AnalysisIssue[] */
    private function findNotInMusly(array $dbPathnames, array $muslyPathnames): array
    {
        $issues = [];

        foreach ($dbPathnames as $pathname => $_) {
            if (!isset($muslyPathnames[$pathname])) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'not_in_musly', [
                    'pathname' => $pathname,
                ]);
            }
        }

        return $issues;
    }

    /** @return AnalysisIssue[] */
    private function findNotInDbButInMusly(array $dbPathnames, array $muslyPathnames): array
    {
        $issues = [];

        foreach ($muslyPathnames as $pathname => $_) {
            if (!isset($dbPathnames[$pathname])) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'not_in_db_but_in_musly', [
                    'pathname' => $pathname,
                ]);
            }
        }

        return $issues;
    }

    /** @return AnalysisIssue[] */
    private function checkFilenameMetadataMismatch(array $tracks): array
    {
        $issues = [];

        foreach ($tracks as $track) {
            $filename = pathinfo($track->getPathname(), PATHINFO_FILENAME);
            $expectedFilenames = $this->buildExpectedFilenames($track);

            if ($expectedFilenames !== [] && !in_array($filename, $expectedFilenames, true)) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'filename_metadata_mismatch', [
                    'guid' => $track->getGuid(),
                    'track_name' => $track->getName(),
                    'filename' => $filename,
                    'expected' => $expectedFilenames[0],
                ]);
            }
        }

        return $issues;
    }

    /** @return string[] */
    private function buildExpectedFilenames(Track $track): array
    {
        $artist = TrackRenameService::sanitizeForFilesystem($track->getArtist());
        $title = TrackRenameService::sanitizeForFilesystem($track->getTitle());

        if (str_contains($track->getPathname(), '/collection/Singles')) {
            $trackNumber = $track->getTrackNumber();

            if (!$trackNumber) {
                return [];
            }

            $paddedNumber = $trackNumber < 10 ? '0' . $trackNumber : (string) $trackNumber;

            return [
                sprintf('%s - %s - %s', $artist, $paddedNumber, $title),
                sprintf('%s. %s - %s', $paddedNumber, $artist, $title),
            ];
        }

        return [sprintf('%s - %s', $artist, $title)];
    }
}
