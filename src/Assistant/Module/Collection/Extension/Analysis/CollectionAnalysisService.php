<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Collection\Repository\CollectionAnalysisRepository;
use Assistant\Module\Track\Extension\TrackService;

final readonly class CollectionAnalysisService
{
    private const array TYPE_LABELS = [
        'empty_metadata' => 'Niekompletne dane',
        'low_bitrate' => 'Niski bitrate',
        'similar_artist' => 'Podobne nazwy wykonawców',
        'similar_publisher' => 'Podobne nazwy wydawców',
        'similar_genre' => 'Podobne nazwy gatunków',
        'suspicious_year' => 'Podejrzany rok',
        'rare_genre' => 'Rzadki gatunek',
        'rare_key' => 'Rzadka tonacja',
    ];

    private const array METADATA_SECTION_ORDER = [
        'empty_metadata',
        'low_bitrate',
        'similar_artist',
        'similar_publisher',
        'similar_genre',
        'suspicious_year',
        'rare_genre',
        'rare_key',
    ];

    public function __construct(
        private CollectionAnalysisRepository $repository,
        private TrackService $trackService,
    ) {
    }

    public function getAnalysisData(): ?array
    {
        $summary = $this->repository->getSummary();

        if (!$summary) {
            return null;
        }

        $issues = $this->repository->getAllIssues();

        $categorized = $this->categorize($issues);

        return [
            'summary' => $summary,
            'categories' => AnalysisCategory::cases(),
            'collection' => $this->buildCollectionData($categorized[AnalysisCategory::COLLECTION->value] ?? []),
            'metadata' => $this->buildMetadataData($categorized[AnalysisCategory::METADATA->value] ?? []),
            'duplicates' => $categorized[AnalysisCategory::POTENTIAL_DUPLICATE->value] ?? [],
            'unignoredCounts' => $this->calculateUnignoredCounts($issues),
        ];
    }

    public function toggleIgnore(string $hash): bool
    {
        return $this->repository->toggleIgnore($hash);
    }

    /** @return array<string, AnalysisIssue[]> */
    private function categorize(array $issues): array
    {
        $result = [];

        foreach ($issues as $issue) {
            $result[$issue->category->value][] = $issue;
        }

        return $result;
    }

    private function buildCollectionData(array $issues): array
    {
        $crossReference = [];
        $filenameMismatch = [];

        foreach ($issues as $issue) {
            if ($issue->type === 'filename_metadata_mismatch') {
                $filenameMismatch[] = $issue;
                continue;
            }

            $pathname = $issue->details['pathname'];

            if (!isset($crossReference[$pathname])) {
                $crossReference[$pathname] = [
                    'pathname' => $pathname,
                    'file' => basename($pathname),
                    'track' => $this->trackService->getByPathname($pathname),
                    'inFilesystem' => true,
                    'inSimilarity' => true,
                    'inDatabase' => true,
                ];
            }

            match ($issue->type) {
                'missing_on_disk' => $crossReference[$pathname]['inFilesystem'] = false,
                'not_in_db' => $crossReference[$pathname]['inDatabase'] = false,
                'not_in_musly' => $crossReference[$pathname]['inSimilarity'] = false,
                'not_in_db_but_in_musly' => $crossReference[$pathname]['inDatabase'] = false,
                default => null,
            };
        }

        return [
            'crossReference' => array_values($crossReference),
            'filenameMismatch' => $filenameMismatch,
        ];
    }

    private function buildMetadataData(array $issues): array
    {
        $grouped = [];

        foreach ($issues as $issue) {
            $grouped[$issue->type][] = $issue;
        }

        $similarityTypes = ['similar_artist', 'similar_publisher', 'similar_genre'];

        foreach ($similarityTypes as $type) {
            if (!isset($grouped[$type])) {
                continue;
            }

            $grouped[$type] = array_map(
                fn (AnalysisIssue $issue) => $this->ensureOutlierFirst($issue),
                $grouped[$type]
            );
        }

        $sections = [];

        foreach (self::METADATA_SECTION_ORDER as $type) {
            if (!isset($grouped[$type])) {
                continue;
            }

            $sections[] = [
                'type' => $type,
                'label' => self::TYPE_LABELS[$type] ?? $type,
                'issues' => $grouped[$type],
            ];
        }

        return $sections;
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

    /** @return array<string, int> */
    private function calculateUnignoredCounts(array $issues): array
    {
        $counts = [];

        foreach (AnalysisCategory::cases() as $category) {
            $counts[$category->value] = 0;
        }

        foreach ($issues as $issue) {
            if (!$issue->ignored) {
                $counts[$issue->category->value]++;
            }
        }

        return $counts;
    }
}
