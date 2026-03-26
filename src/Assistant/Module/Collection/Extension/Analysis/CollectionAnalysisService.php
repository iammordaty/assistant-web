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

    public function getSummary(): ?array
    {
        return $this->repository->getSummary();
    }

    public function getOverviewData(): ?array
    {
        $summary = $this->repository->getSummary();

        if (!$summary) {
            return null;
        }

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

        return ['summary' => $summary, 'rows' => $rows];
    }

    public function getViewData(AnalysisViewType $viewType): array
    {
        $issues = $this->repository->getIssuesByTypes($viewType->issueTypes());

        return match ($viewType) {
            AnalysisViewType::CROSS_REFERENCE => $this->buildCrossReferenceData($issues),
            AnalysisViewType::SIMILAR_ARTIST,
            AnalysisViewType::SIMILAR_PUBLISHER,
            AnalysisViewType::SIMILAR_GENRE => [
                'issues' => array_map($this->ensureOutlierFirst(...), $issues),
            ],
            default => ['issues' => $issues],
        };
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

    private function buildCrossReferenceData(array $issues): array
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

        return ['crossReference' => array_values($crossReference)];
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
