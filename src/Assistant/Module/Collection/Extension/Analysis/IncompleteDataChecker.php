<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Track\Repository\TrackStatsRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;

final readonly class IncompleteDataChecker implements CheckerInterface
{
    private const int MIN_YEAR = 1980;
    private const int PUBLISHER_REQUIRED_SINCE_YEAR = 2025;
    private const int RARE_THRESHOLD = 2;

    public function __construct(
        private TrackRepository $trackRepository,
        private TrackStatsRepository $statsRepository,
    ) {
    }

    public function getCategory(): AnalysisCategory
    {
        return AnalysisCategory::METADATA;
    }

    /** @return AnalysisIssue[] */
    public function check(): array
    {
        $tracks = iterator_to_array($this->trackRepository->findBy(new SearchCriteria()));

        return [
            ...$this->checkSuspiciousYears($tracks),
            ...$this->checkEmptyMetadata($tracks),
            ...$this->checkRareGenres(),
            ...$this->checkRareKeys($tracks),
        ];
    }

    /** @return AnalysisIssue[] */
    private function checkSuspiciousYears(array $tracks): array
    {
        $currentYear = (int) date('Y');
        $maxYear = $currentYear + 1;
        $issues = [];

        foreach ($tracks as $track) {
            $year = $track->getYear();

            if ($year !== null && ($year < self::MIN_YEAR || $year > $maxYear)) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'suspicious_year', [
                    'guid' => $track->getGuid(),
                    'track_name' => $track->getName(),
                    'field' => 'year',
                    'value' => $year,
                ]);
            }
        }

        return $issues;
    }

    /** @return AnalysisIssue[] */
    private function checkEmptyMetadata(array $tracks): array
    {
        $issues = [];

        foreach ($tracks as $track) {
            foreach ($this->getRequiredFields($track) as $fieldName => $getter) {
                $value = $getter($track);

                if ($value === null || $value === '' || $value === 0 || $value === 0.0) {
                    $issues[] = new AnalysisIssue($this->getCategory(), 'empty_metadata', [
                        'guid' => $track->getGuid(),
                        'track_name' => $track->getName(),
                        'field' => $fieldName,
                    ]);
                }
            }
        }

        return $issues;
    }

    private function getRequiredFields(Track $track): array
    {
        $fields = [
            'genre' => fn (Track $t) => $t->getGenre(),
            'year' => fn (Track $t) => $t->getYear(),
            'initial_key' => fn (Track $t) => $t->getInitialKey(),
            'bpm' => fn (Track $t) => $t->getBpm(),
        ];

        $isRecentWithAlbum = $track->getYear() !== null
            && $track->getYear() >= self::PUBLISHER_REQUIRED_SINCE_YEAR
            && $track->getTrackNumber()
            && $track->getAlbum();

        if ($isRecentWithAlbum) {
            $fields['publisher'] = fn (Track $t) => $t->getPublisher();
        }

        return $fields;
    }

    /** @return AnalysisIssue[] */
    private function checkRareGenres(): array
    {
        $genreCounts = $this->statsRepository->getTrackCountByGenre();
        $issues = [];

        foreach ($genreCounts as $genre => $count) {
            if ($count <= self::RARE_THRESHOLD) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'rare_genre', [
                    'value' => $genre,
                    'count' => $count,
                ]);
            }
        }

        return $issues;
    }

    /** @return AnalysisIssue[] */
    private function checkRareKeys(array $tracks): array
    {
        $keyCounts = [];

        foreach ($tracks as $track) {
            $key = $track->getInitialKey();

            if ($key !== null && $key !== '') {
                $keyCounts[$key] = ($keyCounts[$key] ?? 0) + 1;
            }
        }

        $issues = [];

        foreach ($keyCounts as $key => $count) {
            if ($count <= self::RARE_THRESHOLD) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'rare_key', [
                    'value' => $key,
                    'count' => $count,
                ]);
            }
        }

        return $issues;
    }
}
