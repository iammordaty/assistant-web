<?php

namespace Assistant\Module\Common\Extension\Backend;

use Assistant\Module\File\Extension\SplFileInfo;

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

            if (isset($response['command']) === true) {
                $message .= sprintf('%s: ', $response['command']);
            }
            if (isset($response['message']) === true) {
                $message .= $response['message'];
            }

            throw new Exception\AudioDataCalculatorException(
                $message ?: $this->curl->errorMessage,
                $this->curl->errorCode ?: 500
            );
        }

        return $response;
    }
}
