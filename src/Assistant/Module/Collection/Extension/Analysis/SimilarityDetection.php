<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Common\Extension\SlugifyService;

trait SimilarityDetection
{
    private const float SIMILARITY_THRESHOLD = 90.0;

    /** @return AnalysisIssue[] */
    private function findSimilarPairs(
        AnalysisCategory $category,
        string $issueType,
        array $valueCounts,
        SlugifyService $slugifyService,
        ?array $delimiters = null,
    ): array {
        $values = array_keys($valueCounts);
        $slugs = [];

        foreach ($values as $value) {
            $normalized = $delimiters !== null
                ? str_replace($delimiters, ' ', $value)
                : $value;

            $slugs[$value] = $slugifyService->slugify($normalized);
        }

        $reported = [];
        $issues = [];

        foreach ($values as $valueA) {
            foreach ($values as $valueB) {
                if ($valueA === $valueB) {
                    continue;
                }

                $pairKey = $valueA < $valueB ? "$valueA|$valueB" : "$valueB|$valueA";

                if (isset($reported[$pairKey])) {
                    continue;
                }

                $slugA = $slugs[$valueA];
                $slugB = $slugs[$valueB];

                if ($slugA === $slugB) {
                    $isSimilar = true;
                } else {
                    similar_text($slugA, $slugB, $percent);
                    $isSimilar = $percent >= self::SIMILARITY_THRESHOLD;
                }

                if ($isSimilar) {
                    $reported[$pairKey] = true;

                    $issues[] = new AnalysisIssue($category, $issueType, [
                        'value_a' => $valueA,
                        'count_a' => $valueCounts[$valueA],
                        'value_b' => $valueB,
                        'count_b' => $valueCounts[$valueB],
                    ]);
                }
            }
        }

        return $issues;
    }
}
