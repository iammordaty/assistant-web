<?php

namespace Assistant\Module\Common\Extension\Backend;

use Assistant\Module\Common\Extension\Backend\Exception\AudioDataCalculatorException;
use Assistant\Module\Common\Extension\Backend\Exception\SimilarCollectionException;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Curl\Curl;

final class Client
{
    /**
     * @todo Zamienić na guzzle
     */
    private Curl $curl;

    public function __construct()
    {
        $this->curl = new Curl();
        $this->curl->setTimeout(3 * 60);
    }

    public function __destruct()
    {
        $this->curl->close();
    }

    /**
     * @param IncomingTrack|Track $track
     * @return array
     *
     * @throws AudioDataCalculatorException
     */
    public function calculateAudioData(IncomingTrack|Track $track): array
    {
        $response = $this->curl->get(
            sprintf(
                '%s/track/%s',
                'http://backend',
                rawurlencode(ltrim($track->getPathname(), DIRECTORY_SEPARATOR))
            )
        );

        if ($this->curl->error === true) {
            $message = '';

            if (isset($response->command) === true) {
                $message .= sprintf('%s: ', $response->command);
            }
            if (isset($response->message) === true) {
                $message .= $response->message;
            }

            throw new AudioDataCalculatorException(
                $message ?: $this->curl->errorMessage,
                $this->curl->errorCode ?: 500
            );
        }

        return (array) $response;
    }

    /**
     * @param Track $track
     * @return bool
     *
     * @throws SimilarCollectionException
     */
    public function addToSimilarCollection(Track $track): bool
    {
        $response = (array) $this->curl->post(
            sprintf('%s/%s', 'http://backend', 'musly/collection/tracks'),
            json_encode([ 'pathname' => $track->getPathname() ])
        );

        if ($this->curl->error === true) {
            throw new SimilarCollectionException(
                $response->message ?? $this->curl->errorMessage,
                $this->curl->errorCode ?: 500
            );
        }

        return true;
    }

    /**
     * @param Track $track
     * @return array
     *
     * @throws SimilarCollectionException
     */
    public function getSimilarTracks(Track $track): array
    {
        $url = sprintf(
            '%s/%s%s',
            'http://backend',
            'musly/similar/',
            rawurlencode(ltrim($track->getPathname(), DIRECTORY_SEPARATOR))
        );

        $response = $this->curl->get($url);

        if ($this->curl->error === true) {
            throw new SimilarCollectionException(
                $response->message ?? $this->curl->errorMessage,
                $this->curl->errorCode ?: 500
            );
        }

        $similarTracks = [];

        foreach ($response as $similarTrack) {
            $similarTracks[$similarTrack->pathname] = $similarTrack->similarity;
        }

        return $similarTracks;
    }
}
