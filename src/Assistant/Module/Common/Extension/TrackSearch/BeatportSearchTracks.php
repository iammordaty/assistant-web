<?php

namespace Assistant\Module\Common\Extension\TrackSearch;

use Assistant\Module\Common\Extension\Beatport\BeatportTrackBuilder;
use Assistant\Module\Common\Extension\Beatport\BeatportTrack;
use Assistant\Module\Common\Extension\BeatportApiClient;
use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;

final class BeatportSearchTracks
{
    private BeatportApiClient $client;

    private BeatportTrackBuilder $trackBuilder;

    private SlugifyInterface $slugify;

    public function __construct(
        BeatportApiClient $client,
        BeatportTrackBuilder $beatportTrackBuilder,
        ?SlugifyInterface $slugify = null
    ) {
        $this->client = $client;
        $this->trackBuilder = $beatportTrackBuilder;
        $this->slugify = $slugify ?: new Slugify([ 'separator' => ' ' ]);
    }

    /**
     * @param string $query
     * @return BeatportTrack[]
     */
    public function __invoke(string $query): array
    {
        $response = $this->client->search([ 'query' => $this->slugify->slugify($query)]);

        $rawTracks = array_filter(
            $response['results'],
            static fn ($rawTrack) => $rawTrack['type'] === BeatportTrackBuilder::ITEM_TYPE_TRACK
        );

        $beatportTracks = array_map(
            fn($rawTrack) => $this->trackBuilder->fromRawTrack($rawTrack),
            $rawTracks
        );

        return $beatportTracks;
    }
}
