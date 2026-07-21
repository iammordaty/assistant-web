<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use SplFileInfo;

final class MusicClassifierRequestException extends MusicClassifierException
{
    public function __construct(SplFileInfo $track, GuzzleException $e)
    {
        $message = sprintf(
            'Music classifier request failed for "%s": %s',
            $track->getPathname(),
            self::getProbableErrorMessage($e),
        );

        parent::__construct($message, previous: $e);
    }

    private static function getProbableErrorMessage(GuzzleException $e): string
    {
        // serwis zwraca błędy żądania w formacie { "error": { "message": "..." } }
        if ($e instanceof BadResponseException) {
            $contents = json_decode($e->getResponse()->getBody()->getContents(), true);

            if (isset($contents['error']['message'])) {
                return $contents['error']['message'];
            }
        }

        return $e->getMessage();
    }
}
