<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Collection\Repository\CollectionAnalysisRepository;
use Assistant\Module\Track\Extension\TrackService;

final readonly class CollectionAnalysisService
{
    public function __construct(
        private CollectionAnalysisRepository $repository,
        private TrackService $trackService,
    ) {
    }

    public function getOverviewSummary(): ?array
    {
        $summary = $this->repository->getSummary();

        if (!$summary) {
            return null;
        }

        $summary['rows'] = $this->getOverviewRows();

        return $summary;
    }

    public function getSummary(): ?array
    {
        return $this->repository->getSummary();
    }

    private function getOverviewRows(): array
    {
        $issues = $this->repository->getAllIssues();
        $typeCounts = $this->countIssuesByType($issues);

        $rows = [];

        foreach (AnalysisViewType::cases() as $viewType) {
            $total = 0;
            $unignored = 0;

            foreach ($viewType->issueTypes() as $issueType) {
                $total += $typeCounts[$issueType]['total'] ?? 0;
                $unignored += $typeCounts[$issueType]['unignored'] ?? 0;
            }

            if ($total > 0) {
                $rows[] = [
                    'viewType' => $viewType,
                    'total' => $total,
                    'unignored' => $unignored,
                ];
            }
        }

        return $rows;
    }

    public function getCrossReference(): array
    {
        return $this->buildCrossReference($this->getIssues(AnalysisViewType::CROSS_REFERENCE));
    }

    public function getFilenameMismatchIssues(): array
    {
        return $this->getIssues(AnalysisViewType::FILENAME_MISMATCH);
    }

    public function getEmptyMetadataIssues(): array
    {
        return $this->getIssues(AnalysisViewType::EMPTY_METADATA);
    }

    public function getLowAudioQualityIssues(): array
    {
        return $this->getIssues(AnalysisViewType::LOW_AUDIO_QUALITY);
    }

    public function getSimilarArtistIssues(): array
    {
        return array_map($this->ensureOutlierFirst(...), $this->getIssues(AnalysisViewType::SIMILAR_ARTIST));
    }

    public function getSimilarPublisherIssues(): array
    {
        return array_map($this->ensureOutlierFirst(...), $this->getIssues(AnalysisViewType::SIMILAR_PUBLISHER));
    }

    public function getSimilarGenreIssues(): array
    {
        return array_map($this->ensureOutlierFirst(...), $this->getIssues(AnalysisViewType::SIMILAR_GENRE));
    }

    public function getSuspiciousYearIssues(): array
    {
        return $this->getIssues(AnalysisViewType::SUSPICIOUS_YEAR);
    }

    public function getRareGenreIssues(): array
    {
        return $this->getIssues(AnalysisViewType::RARE_GENRE);
    }

    public function getRareKeyIssues(): array
    {
        return $this->getIssues(AnalysisViewType::RARE_KEY);
    }

    public function getPotentialDuplicateIssues(): array
    {
        return $this->getIssues(AnalysisViewType::POTENTIAL_DUPLICATE);
    }

    public function toggleIgnore(string $hash): bool
    {
        return $this->repository->toggleIgnore($hash);
    }

    /** @return array<string, array{total: int, unignored: int}> */
    private function countIssuesByType(array $issues): array
    {
        $counts = [];

        foreach ($issues as $issue) {
            $counts[$issue->type] ??= ['total' => 0, 'unignored' => 0];
            $counts[$issue->type]['total']++;

            if (!$issue->ignored) {
                $counts[$issue->type]['unignored']++;
            }
        }

        return $counts;
    }

    private function getIssues(AnalysisViewType $viewType): array
    {
        return $this->repository->getIssuesByTypes($viewType->issueTypes());
    }

    private function buildCrossReference(array $issues): array
    {
        $crossReference = [];

        foreach ($issues as $issue) {
            $pathname = $issue->details['pathname'] ?? ($issue->details['path'] ?? 'unknown');

            $crossReference[$pathname] ??= [
                'pathname' => $pathname,
                'file' => basename($pathname),
                'track' => $this->trackService->getByPathname($pathname),
                'inFilesystem' => true,
                'inSimilarity' => true,
                'inDatabase' => true,
            ];

            match ($issue->type) {
                'missing_on_disk' => $crossReference[$pathname]['inFilesystem'] = false,
                'not_in_db' => $crossReference[$pathname]['inDatabase'] = false,
                'not_in_musly' => $crossReference[$pathname]['inSimilarity'] = false,
                'not_in_db_but_in_musly' => $crossReference[$pathname]['inDatabase'] = false,
                default => null,
            };
        }

        return array_values($crossReference);
    }

    private function ensureOutlierFirst(AnalysisIssue $issue): AnalysisIssue
    {
        $countA = $issue->details['count_a'] ?? 0;
        $countB = $issue->details['count_b'] ?? 0;

        if ($countA <= $countB) {
            return $issue;
        }

        $details = $issue->details;
        $details['value_a'] = $issue->details['value_b'];
        $details['count_a'] = $issue->details['count_b'];
        $details['value_b'] = $issue->details['value_a'];
        $details['count_b'] = $issue->details['count_a'];

        if (isset($details['tracks_a']) || isset($details['tracks_b'])) {
            $tracksA = $details['tracks_a'] ?? null;
            $details['tracks_a'] = $details['tracks_b'] ?? null;
            $details['tracks_b'] = $tracksA;
        }

        return new AnalysisIssue($issue->category, $issue->type, $details, $issue->ignored, $issue->mongoId);
    }
}
