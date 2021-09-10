<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Track\Extension\TrackService;
use Fig\Http\Message\StatusCodeInterface;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

final class ContentsController
{
    public function __construct(
        private Logger $logger,
        private TrackService $trackService,
    ) {
    }

    public function get(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackService->getByGuid($guid);

        if (!$track) {
            $error = sprintf("Track with guid \"%s\" doesn't exist.", $guid);

            $error = $response
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

        $contentDisposition = sprintf('inline; filename="%s - %s"', $track->getArtist(), $track->getTitle());

        $response = $response
            ->withFile($track->getPathname())
            ->withHeader('Content-Disposition', $contentDisposition)
            ->withHeader('Content-Length', $track->getFile()->getSize());

        return $response;
    }
}
