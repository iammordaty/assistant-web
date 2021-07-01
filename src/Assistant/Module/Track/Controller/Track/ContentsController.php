<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\TrackService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Factory\StreamFactory;

final class ContentsController
{
    public function __construct(
        private RouteResolver $routeResolver,
        private TrackService $trackService,
    ) {
    }

    public function get(Request $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');
        $track = $this->trackService->getByGuid($guid);

        if (!$track || !is_readable($track->getPathname())) {
            $route = Route::create('search.simple.index')->withQuery([ 'query' => str_replace('-', ' ', $guid) ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(404);

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
