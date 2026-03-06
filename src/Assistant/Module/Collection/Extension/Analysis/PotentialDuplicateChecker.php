<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;

final readonly class PotentialDuplicateChecker implements CheckerInterface
{
    private const float SIMILARITY_THRESHOLD = 96.0;

    public function __construct(
        private TrackRepository $trackRepository,
        private SlugifyService $slugifyService,
    ) {
    }

    public function getCategory(): AnalysisCategory
    {
        return AnalysisCategory::POTENTIAL_DUPLICATE;
    }

    /** @return AnalysisIssue[] */
    public function check(): array
    {
        $tracks = [];

        foreach ($this->trackRepository->findBy(new SearchCriteria()) as $track) {
            $tracks[] = [
                'guid' => $track->getGuid(),
                'name' => $track->getName(),
                'pathname' => $track->getPathname(),
            ];
        }

        return [
            ...$this->findGuidDuplicates($tracks),
            ...$this->findSimilarTrackNames($tracks),
        ];
    }

    /** @return AnalysisIssue[] */
    private function findGuidDuplicates(array $tracks): array
    {
        $guidMap = [];

        foreach ($tracks as $track) {
            $guidMap[$track['guid']] = $track;
        }

        $reported = [];
        $issues = [];

        foreach ($guidMap as $guid => $track) {
            if (isset($reported[$guid])) {
                continue;
            }

            $baseGuid = preg_replace('/-\d+$/', '', $guid);

            if ($baseGuid === $guid || !isset($guidMap[$baseGuid])) {
                continue;
            }

            $reported[$guid] = true;
            $reported[$baseGuid] = true;

            $issues[] = new AnalysisIssue($this->getCategory(), 'potential_duplicate', [
                'guid_base' => $baseGuid,
                'value_a' => $guidMap[$baseGuid]['name'],
                'guid_a' => $baseGuid,
                'value_b' => $track['name'],
                'guid_b' => $guid,
            ]);
        }

        return $issues;
    }

    /** @return AnalysisIssue[] */
    private function findSimilarTrackNames(array $tracks): array
    {
        $nameToGuid = [];

        foreach ($tracks as $track) {
            $nameToGuid[$track['name']] = $track['guid'];
        }

        $names = array_keys($nameToGuid);
        $slugs = [];

        foreach ($names as $name) {
            $slugs[$name] = $this->slugifyService->slugify($name);
        }

        $reported = [];
        $issues = [];

        foreach ($names as $nameA) {
            foreach ($names as $nameB) {
                if ($nameA === $nameB) {
                    continue;
                }

                $pairKey = $nameA < $nameB ? "$nameA|$nameB" : "$nameB|$nameA";

                if (isset($reported[$pairKey])) {
                    continue;
                }

                $slugA = $slugs[$nameA];
                $slugB = $slugs[$nameB];

                if ($slugA === $slugB) {
                    $isSimilar = true;
                } else {
                    similar_text($slugA, $slugB, $percent);
                    $isSimilar = $percent >= self::SIMILARITY_THRESHOLD;
                }

                if ($isSimilar) {
                    $reported[$pairKey] = true;

                    $issues[] = new AnalysisIssue($this->getCategory(), 'potential_duplicate', [
                        'value_a' => $nameA,
                        'guid_a' => $nameToGuid[$nameA],
                        'value_b' => $nameB,
                        'guid_b' => $nameToGuid[$nameB],
                    ]);
                }
            }
        }

        return $issues;
    }
}
