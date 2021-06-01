<?php

namespace Assistant\Module\Common\Extension\Beatport;

use Assistant\Module\Common\Extension\BeatportApiClient;
use Assistant\Module\Common\Extension\TrackSearch\GoogleBeatportSearchResult;

final class BeatportTrackBuilder
{
    public const ITEM_TYPE_TRACK = 'track';

    private BeatportApiClient $client;

    public function __construct(BeatportApiClient $client)
    {
        $this->client = $client;
    }

    public function fromTrackId(int $trackId): ?BeatportTrack
    {
        [ 'results' => $rawTracks ] = $this->client->tracks([ 'id' => $trackId ]);

        if (empty($rawTracks)) {
            return null;
        }

        $rawTrack = reset($rawTracks);
        $beatportTrack = $this->fromRawTrack($rawTrack);

        return $beatportTrack;
    }

    public function fromRawTrack(array $rawTrack): ?BeatportTrack
    {
        if ($rawTrack['type'] !== self::ITEM_TYPE_TRACK) {
            throw new \RuntimeException('Unsupported type received: ' . $rawTrack['type']);
        }

        $chartsIds = array_map(static fn($chart) => $chart['id'], $rawTrack['charts']);
        [ 'results' => $rawCharts ] = $this->client->charts([ 'ids' => implode(',', $chartsIds) ]);

        $rawTrack['charts'] = $rawCharts;

        $beatportTrack = BeatportTrack::create($rawTrack);

        return $beatportTrack;
    }

    public function fromGoogleBeatportSearchResult(GoogleBeatportSearchResult $result): ?BeatportTrack
    {
        $beatportTrack = $this->fromTrackId($result->getId());

        return $beatportTrack;
    }
}
