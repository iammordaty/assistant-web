<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;

final readonly class LowBitrateChecker implements CheckerInterface
{
    private const int MIN_BITRATE_KBPS = 192;

    public function __construct(
        private TrackRepository $trackRepository,
    ) {
    }

    public function getCategory(): AnalysisCategory
    {
        return AnalysisCategory::METADATA;
    }

    /** @return AnalysisIssue[] */
    public function check(): array
    {
        $tracks = $this->trackRepository->findBy(new SearchCriteria());
        $issues = [];

        foreach ($tracks as $track) {
            if (!is_readable($track->getPathname())) {
                continue;
            }

            $bitrate = $track->getFile()->getBitrate();

            if ($bitrate !== null && $bitrate < self::MIN_BITRATE_KBPS) {
                $issues[] = new AnalysisIssue($this->getCategory(), 'low_bitrate', [
                    'guid' => $track->getGuid(),
                    'track_name' => $track->getName(),
                    'bitrate' => $bitrate,
                ]);
            }
        }

        return $issues;
    }
}
