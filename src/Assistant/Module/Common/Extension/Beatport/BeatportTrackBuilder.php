<?php

namespace Assistant\Module\Common\Extension\Beatport;

use Assistant\Module\Common\Extension\BeatportApiClientInterface;
use Assistant\Module\Common\Extension\TrackSearch\GoogleBeatportSearchResult;
use Fig\Http\Message\StatusCodeInterface;

final readonly class BeatportTrackBuilder
{
    public function __construct(private BeatportApiClientInterface $client)
    {
    }

    public function fromTrackId(int $trackId): ?BeatportTrack
    {
        try {
            $rawTrack = $this->client->track($trackId);

            $rawCharts = $this->client->charts([ 'track_id' => $rawTrack['id'] ]);
            $rawTrack['charts'] = $rawCharts['results'];

            $rawReleases = $this->client->releases([ 'id' => $rawTrack['release']['id'] ]);
            $rawTrack['release'] = $rawReleases['results'][0];
        } catch (\Exception $e) {
            if ($e->getCode() !== StatusCodeInterface::STATUS_FORBIDDEN) { // 403: Territory Restriction
                // yolo, przydałoby się to komunikować na froncie w bardziej przystępny sposób
                var_dump($e->getMessage());
            }

            $rawTrack = null;
        }

        if (!$rawTrack) {
            return null;
        }

        $beatportTrack = self::createBeatportTrack($rawTrack);

        return $beatportTrack;
    }

    public function fromBeatportSearchResult(array $result): ?BeatportTrack
    {
        // "number" to jedyne pole, którego brakuje do utworzenia pełnego obiektu BeatportTrack,
        // ale jego brak jest irytujący. Z drugiej strony, wyszukanie utworów, a następnie pobranie
        // ich w całości wydłuża proces ładowania się strony. Przemyśleć, może da się to załatwić
        // poprzez wysyłanie żądań w sposób równoległy.

        $rawTrack = array_merge([ 'number' => null ], $result);

        $beatportTrack = $this->fromTrackId($rawTrack['id']);

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
