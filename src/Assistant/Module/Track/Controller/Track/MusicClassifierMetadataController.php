<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Repository\MusicClassifierMetadataRepository;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final readonly class MusicClassifierMetadataController
{
    public function __construct(
        private MusicClassifierMetadataRepository $musicClassifierMetadataRepository,
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

        $metadata = $this->musicClassifierMetadataRepository->getByTrackGuid($track->getGuid());

        if (!$metadata) {
            return $response
                ->withJson([ 'message' => sprintf('Music classifier metadata for "%s" does not exist.', $guid) ])
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $filename = str_replace([ '"', "\r", "\n" ], '', sprintf('%s.json', $track->getGuid()));

        return $response
            ->withHeader('Content-Disposition', sprintf('inline; filename="%s"', $filename))
            ->withJson($metadata->toArray());
    }
}
