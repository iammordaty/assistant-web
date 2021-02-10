<?php

namespace Assistant\Module\Common\Extension\Backend;

use Assistant\Module\Common\Extension\Backend\Exception\AudioDataCalculatorException;
use Assistant\Module\Common\Extension\Backend\Exception\SimilarCollectionException;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Track;

use Curl\Curl;

class Client
{
    /**
     * Obiekt klasy Curl
     *
     * @var Curl
     */
    private $curl;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->curl = new Curl();
        $this->curl->setTimeout(3 * 60);
    }

    /**
     * Destruktor
     */
    public function __destruct()
    {
        $this->curl->close();
    }

    /**
     * @param SplFileInfo $node
     * @return array
     * @throws AudioDataCalculatorException
     */
    public function calculateAudioData(SplFileInfo $node)
    {
        $response = $this->curl->get(
            sprintf(
                '%s/track/%s',
                'http://backend',
                rawurlencode(ltrim($node->getRelativePathname(), DIRECTORY_SEPARATOR))
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
     * @param Track\Model\Track $track
     * @return bool
     * @throws SimilarCollectionException
     */
    public function addToSimilarCollection(Track\Model\Track $track)
    {
        $response = (array) $this->curl->post(
            sprintf('%s/%s', 'http://backend', 'musly/collection/tracks'),
            json_encode([ 'pathname' => $track->pathname ])
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
     * @param Track\Model\Track $track
     * @return array
     * @throws SimilarCollectionException
     */
    public function getSimilarTracks(Track\Model\Track $track)
    {
        $response = $this->curl->get(
            sprintf(
                '%s/%s%s',
                'http://backend',
                'musly/similar',
                rawurlencode($track->pathname)
            )
        );

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
