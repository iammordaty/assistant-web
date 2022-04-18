<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Track\Extension\TrackService;
use Fig\Http\Message\StatusCodeInterface;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class ContentsController
{
    public function __construct(
        private readonly Logger $logger,
        private readonly TrackService $trackService,
    ) {
    }

    public function get(ServerRequest $request, Response $response): ResponseInterface
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackService->getByGuid($guid);

        if (!$track) {
            $error = sprintf("Track with guid \"%s\" doesn't exist.", $guid);

            $response = $response
                ->withJson([ 'error' => $error ])
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

            return $response;
        }

        if (!is_readable($track->getPathname())) {
            $this->logger->error('File is not readable', [
                'pathname' => $track->getPathname(),
            ]);

            $error = sprintf("File \"%s\" is not readable.", $track->getPathname());

            return $response
                ->withJson([ 'error' => $error ])
                ->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }

        $response = $response->withFileDownload(
            file: $track->getPathname(),
            name: $track->getName(),
        );

        return $response;
    }
}
