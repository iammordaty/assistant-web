<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierException;
use Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierService;
use Assistant\Module\Track\Extension\TrackService;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use SplFileInfo;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final readonly class MusicClassifierMetadataController
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
            return $this->getTrackNotFoundResponse($response, $guid);
        }

        return $this->getResultResponse($response, $track->getFile(), $guid);
    }

    public function getIncoming(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->createFromFile($pathname);

        if (!$track) {
            return $this->getTrackNotFoundResponse($response, $pathname);
        }

        return $this->getResultResponse($response, $track->getFile(), $track->getGuid());
    }

    private function getResultResponse(Response $response, SplFileInfo $file, string $name): ResponseInterface
    {
        try {
            $result = $this->musicClassifierService->analyze($file);
        } catch (MusicClassifierException $e) {
            return $this->getClassifierErrorResponse($response, $e);
        }

        $filename = str_replace([ '"', "\r", "\n" ], '', sprintf('%s.json', $name));

        return $response
            ->withHeader('Content-Disposition', sprintf('inline; filename="%s"', $filename))
            ->withJson($result->getRawResult());
    }

    private function getTrackNotFoundResponse(Response $response, string $name): ResponseInterface
    {
        return $response
            ->withJson([ 'message' => sprintf('Track "%s" does not exist.', $name) ])
            ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
    }

    private function getClassifierErrorResponse(Response $response, MusicClassifierException $e): ResponseInterface
    {
        return $response
            ->withJson([ 'message' => $e->getMessage() ])
            ->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
    }
}
