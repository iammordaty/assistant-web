<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\Redirect;
use Assistant\Module\Track\Extension\TrackService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Factory\StreamFactory;

final class ContentsController
{
    public function __construct(private TrackService $trackService)
    {
    }

    public function get(Request $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackService->findOneByGuid($guid);

        if (!$track || !is_readable($track->getPathname())) {
            $redirect = Redirect::create(
                request: $request,
                routeName: 'search.simple.index',
                queryParams: [ 'query' => str_replace('-', ' ', $guid) ],
                status: 404,
            );

            return $redirect;
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
