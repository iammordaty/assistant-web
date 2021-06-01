<?php

namespace Assistant\Module\Common\Extension\TrackSearch;

use Assistant\Module\Common\Extension\Beatport\BeatportTrack;
use Assistant\Module\Common\Extension\Beatport\BeatportTrackBuilder;

final class GoogleBeatportSearchTracks
{
    private GoogleSearchApiClient $client;

    private BeatportTrackBuilder $trackBuilder;

    public function __construct(GoogleSearchApiClient $client, BeatportTrackBuilder $beatportTrackBuilder)
    {
        $this->client = $client;
        $this->trackBuilder = $beatportTrackBuilder;
    }

    /**
     * @param string $query
     * @return BeatportTrack[]
     */
    public function __invoke(string $query): array
    {
        $response = $this->client->search(trim($query));

        $results = array_map(
            static fn($rawResult) => GoogleBeatportSearchResult::factory($rawResult),
            $response
        );

        // tutaj może dałoby się żądania wysyłać równolegle
        // https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests

        $beatportTracks = array_map(
            fn($result) => $this->trackBuilder->fromGoogleBeatportSearchResult($result),
            $results
        );

        $beatportTracks = array_merge(
            array_filter($beatportTracks, static fn($beatportTrack) => $beatportTrack !== null)
        );

        return $beatportTracks;
    }
}
