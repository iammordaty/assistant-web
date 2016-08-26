<?php

namespace Assistant\Module\Common\Extension\Backend;

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
        $this->curl->setTimeout(60);
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
     * @throws Exception\AudioDataCalculatorException
     */
    public function calculateAudioData(SplFileInfo $node)
    {
        $response = (array) $this->curl->get(
            sprintf(
                '%s/track/%s',
                'http://assistant-backend',
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

            throw new Exception\AudioDataCalculatorException(
                $message ?: $this->curl->errorMessage,
                $this->curl->errorCode ?: 500
            );
        }

        return $response;
    }

    /**
     * @param Track\Model\Track $track
     * @param array $similarKeys
     * @param array $similarYears
     * @return bool
     * @throws Exception\SimilarCollectionException
     */
    public function addToSimilarCollection(Track\Model\Track $track, array $similarKeys, array $similarYears)
    {
        $response = (array) $this->curl->post(
            sprintf('%s/%s', 'http://assistant-backend', 'musly/collection/tracks'),
            json_encode(
                [
                    'pathname' => $track->pathname,
                    'initial_key' => $similarKeys,
                    'year' => $similarYears,
                ]
            )
        );

        if ($this->curl->error === true) {
            throw new Exception\SimilarCollectionException(
                isset($response->message) ? $response->message : $this->curl->errorMessage,
                $this->curl->errorCode ?: 500
            );
        }

        return true;
    }

    /**
     * @param Track\Model\Track $track
     * @param array $similarKeys
     * @param array $similarYears
     * @return array
     * @throws Exception\SimilarCollectionException
     */
    public function getSimilarTracks(Track\Model\Track $track, array $similarKeys, array $similarYears)
    {
        $query = http_build_query([ 'initial_key' => $similarKeys, 'year' => $similarYears ]);

        $response = $this->curl->get(
            sprintf(
                '%s/%s%s?%s',
                'http://assistant-backend',
                'musly/similar',
                rawurlencode($track->pathname),
                preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $query)
            )
        );

        if ($this->curl->error === true) {
            throw new Exception\SimilarCollectionException(
                isset($response->message) ? $response->message : $this->curl->errorMessage,
                $this->curl->errorCode ?: 500
            );
        }

        return array_map(
            function ($similarTrack) {
                return [
                    'pathname' => str_replace('/collection', '', $similarTrack->pathname),
                    'similarity' => $similarTrack->similarity,
                ];
            },
            $response
        );
    }
}
