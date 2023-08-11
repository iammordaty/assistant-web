<?php

namespace Assistant\Module\Common\Extension\TrackSearch;

use Assistant\Module\Common\Extension\Beatport\BeatportTrackBuilder;
use Assistant\Module\Common\Extension\Beatport\BeatportTrack;
use Assistant\Module\Common\Extension\BeatportApiClientInterface;
use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;

final class BeatportSearchTracks
{
    private BeatportApiClientInterface $client;

    private BeatportTrackBuilder $trackBuilder;

    private SlugifyInterface $slugify;

    public function __construct(
        BeatportApiClientInterface $client,
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
        $response = $this->client->search([
            'q' => $this->slugify->slugify($query),
            'type' => 'tracks',
            'per_page' => 10,
        ]);

        $beatportTracks = array_map(
            fn ($rawTrack) => $this->trackBuilder->fromBeatportSearchResult($rawTrack),
            $response['tracks']
        );

        return $beatportTracks;
    }
}
