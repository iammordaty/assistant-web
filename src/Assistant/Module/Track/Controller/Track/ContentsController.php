<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Track\Repository\TrackRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\StreamFactory;

final class ContentsController
{
    public function __construct(private TrackRepository $trackRepository)
    {
    }

    public function get(Request $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackRepository->getOneByGuid($guid);

        if (!$track || !is_readable($track->getPathname())) {
            throw new HttpNotFoundException($request);
        }

        $body = (new StreamFactory())->createStreamFromFile($track->getPathname());
        $contentDisposition = sprintf('inline; filename="%s - %s"', $track->getArtist(), $track->getTitle());

        $response = $response
            ->withBody($body)
            ->withHeader('Content-Disposition', $contentDisposition)
            ->withHeader('Content-Length', filesize($track->getPathname()))
            ->withHeader('Content-Type', 'audio/mpeg');

        return $response;
    }
}
