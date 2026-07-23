<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;

final readonly class LowAudioQualityChecker implements CheckerInterface
{
    private const int MIN_BITRATE_CBR = 190;
    private const int MIN_BITRATE_VBR = 220;

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

            $file = $track->getFile();

            $bitrate = $file->getBitrate();
            $bitrateMode = $file->getBitrateMode();
            $channelMode = $file->getChannelMode();

            $isMono = $channelMode !== null && strtolower($channelMode) === 'mono';
            $isLowBitrate = $bitrate !== null && $this->isBelowThreshold($bitrate, $bitrateMode);

            if (!$isMono && !$isLowBitrate) {
                continue;
            }

            $reasons = [];

            if ($isLowBitrate) {
                $reasons[] = $bitrate . ' kbps' . ($bitrateMode ? ' (' . strtoupper($bitrateMode) . ')' : '');
            }

            if ($isMono) {
                $reasons[] = 'mono';
            }

            $issues[] = new AnalysisIssue($this->getCategory(), 'low_audio_quality', [
                'guid' => $track->getGuid(),
                'track_name' => $track->getName(),
                'bitrate' => $bitrate,
                'bitrate_mode' => $bitrateMode,
                'channel_mode' => $channelMode,
                'is_low_bitrate' => $isLowBitrate,
                'is_mono' => $isMono,
                'reason' => implode(', ', $reasons),
            ]);
        }

        return $issues;
    }

    private function isBelowThreshold(int $bitrate, ?string $bitrateMode): bool
    {
        $threshold = strtolower($bitrateMode ?? '') === 'vbr'
            ? self::MIN_BITRATE_VBR
            : self::MIN_BITRATE_CBR;

        return $bitrate < $threshold;
    }
}
