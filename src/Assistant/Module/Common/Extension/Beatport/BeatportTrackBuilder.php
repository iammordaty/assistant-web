<?php

namespace Assistant\Module\Common\Extension\Beatport;

use Assistant\Module\Common\Extension\BeatportApiClientInterface;
use Assistant\Module\Common\Extension\TrackSearch\GoogleBeatportSearchResult;

final class BeatportTrackBuilder
{
    private BeatportApiClientInterface $client;

    public function __construct(BeatportApiClientInterface $client)
    {
        $this->client = $client;
    }

    public function fromTrackId(int $trackId): ?BeatportTrack
    {
        try {
            $rawTrack = $this->client->track($trackId);

            [ 'results' => $rawCharts ] = $this->client->charts([ 'track_id' => $rawTrack['id'] ]);
            $rawTrack['charts'] = $rawCharts;
        } catch (\Exception $e) { // yolo, obejście 403 - territory restriction
            // var_dump($e->getMessage());

            $rawTrack = null;
        }

        if (empty($rawTrack)) {
            return null;
        }

        $beatportTrack = self::createBeatportTrack($rawTrack);

        return $beatportTrack;
    }

    public function fromBeatportSearchResult(array $result): ?BeatportTrack
    {
        // Być może to powinien być oddzielny typ (na zasadzie GoogleBeatportSearchResult), ale na tę chwilę
        // "number" to jedyne pole, którego brakuje do utworzenia pełnego obiektu BeatportTrack... co robić?
        // Poza tym, wyniki zwracane przez wyszukiwarkę beatport-u są gorszej jakości niż z google-a, więc
        // warto zastanowić się czy utrzymywać tę metodę

        $rawTrack = array_merge([ 'number' => null ], $result);
        $beatportTrack = self::createBeatportTrack($rawTrack);

        return $beatportTrack;
    }

    public function fromGoogleBeatportSearchResult(GoogleBeatportSearchResult $result): ?BeatportTrack
    {
        $beatportTrack = $this->fromTrackId($result->getId());

        return $beatportTrack;
    }

    private static function createBeatportTrack(array $rawTrack): BeatportTrack
    {
        $beatportTrack = BeatportTrack::create($rawTrack);

        return $beatportTrack;
    }
}
