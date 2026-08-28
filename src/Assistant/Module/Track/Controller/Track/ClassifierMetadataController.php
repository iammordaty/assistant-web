<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Track\Extension\TrackService;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final readonly class ClassifierMetadataController
{
    public function __construct(
        private MusicClassifierService $musicClassifierService,
        private TrackService $trackService,
    ) {
    }

    public function get(ServerRequest $request, Response $response): ResponseInterface
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackService->getByGuid($guid);

        if (!$track) {
            return $response
                ->withJson([ 'message' => sprintf('Track "%s" does not exist.', $guid) ])
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        try {
            $result = $this->musicClassifierService->getResult($track->getFile());
        } catch (MusicClassifierException $e) {
            return $response
                ->withJson([ 'message' => $e->getMessage() ])
                ->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }

        if (!$result) {
            return $response
                ->withJson([ 'message' => sprintf('Classifier metadata for "%s" does not exist.', $guid) ])
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $filename = str_replace(
            [ '"', "\r", "\n" ],
            '',
            $result->getFile()?->getBasename() ?? sprintf('%s.json', $guid),
        );

        return $response
            ->withHeader('Content-Disposition', sprintf('inline; filename="%s"', $filename))
            ->withJson($result->getRawResult());
    }
}
