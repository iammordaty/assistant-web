<?php

namespace Assistant\Module\Common\Extension\Backend;

use Assistant\Module\Common\Extension\Backend\Exception\AudioDataCalculatorException;
use Assistant\Module\Common\Extension\Backend\Exception\SimilarCollectionException;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Task\AudioDataCalculatorTask;
use Curl\Curl;
use SplFileInfo;

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
     * @todo To powinno przyjmować obiekt Track
     * @see AudioDataCalculatorTask::execute()
     *
     * @param SplFileInfo $node
     * @return array
     *
     * @throws AudioDataCalculatorException
     */
    public function calculateAudioData(SplFileInfo $node): array
    {
        // @fixme: do czasu poprawienia w backendzie
        $tempRootDir = '/collection';
        $tempPathname = str_replace($tempRootDir, '', $node->getPathname());

        $response = $this->curl->get(
            sprintf(
                '%s/track/%s',
                'http://backend',
                rawurlencode(ltrim($tempPathname, DIRECTORY_SEPARATOR))
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
        // @fixme: do czasu poprawienia w backendzie
        $tempRootDir = '/collection';
        $tempPathname = str_replace($tempRootDir, '', $track->getPathname());

        $response = (array) $this->curl->post(
            sprintf('%s/%s', 'http://backend', 'musly/collection/tracks'),
            json_encode([ 'pathname' => $tempPathname ])
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
        // @fixme: do czasu poprawienia w backendzie
        $tempRootDir = '/collection';
        $tempPathname = str_replace($tempRootDir, '', $track->getPathname());

        $response = $this->curl->get(
            sprintf(
                '%s/%s%s',
                'http://backend',
                'musly/similar',
                rawurlencode($tempPathname)
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
            // @fixme: do czasu poprawienia w backendzie
            $tempPathname = $tempRootDir . $similarTrack->pathname;

            $similarTracks[$tempPathname] = $similarTrack->similarity;
        }

        return $similarTracks;
    }
}
