<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\Messages;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Extension\TrackUpdateService;
use Assistant\Module\Track\Extension\UpdateTrackCommand;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final class EditController
{
    public function __construct(
        private RouteResolver $routeResolver,
        private TrackService $trackService,
        private TrackUpdateService $trackUpdateService,
        private Messages $messages,
        private Logger $logger,
        private Twig $view,
    ) {
    }

    public function edit(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->getByPathname($pathname);

        if (!$track) {
            return $this->getNotFoundRedirect($response, $pathname);
        }

        $route = Route::create('track.track.index')->withParams([ 'guid' => $track->getGuid() ]);
        $returnUrl = $this->routeResolver->resolve($route);

        return $this->view->render($response, '@track/track/edit/edit.twig', [
            'menu' => 'track',
            'track_data' => [
                'fields' => self::getTrackEditableFields(),
                'options' => self::getTrackOptions(),
            ],
            'pathname' => $pathname,
            'track' => $track,
            'return_url' => $returnUrl,
        ]);
    }

    public function save(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->getByPathname($pathname);

        if (!$track) {
            return $this->getNotFoundRedirect($response, $pathname);
        }

        $editUrl = $this->routeResolver->resolve(
            Route::create('track.edit.edit')->withParams([ 'pathname' => $pathname ])
        );

        try {
            $updateCommand = UpdateTrackCommand::fromRequest($request);
            $result = $this->trackUpdateService->update($track, $updateCommand);
        } catch (\Throwable $e) {
            $this->logger->error('Track update failed', [ 'pathname' => $pathname, 'error' => $e->getMessage() ]);
            $this->messages->addError($e->getMessage());

            return $response->withRedirect($editUrl);
        }

        $this->messages->addSuccess('Zapisano zmiany w utworze.');

        foreach ($result->warnings as $warning) {
            $this->messages->addWarning($warning);
        }

        $route = Route::create('track.track.index')->withParams([ 'guid' => $result->track->getGuid() ]);
        $redirectUrl = $this->routeResolver->resolve($route);

        return $response->withRedirect($redirectUrl);
    }

    /** @todo Przenieść do innej klasy */
    private static function getTrackEditableFields(): array
    {
        // todo: dodać typ - array (dla pola artists i tags), string i date

        return [
            [ 'field' => 'guid', 'title' => 'GUID', 'type' => 'string' ],
            // [ 'field' => 'pathname', 'title' => 'Nazwa pliku', 'type' => 'string' ],
            [ 'field' => 'artist', 'title' => 'Wykonawca', 'type' => 'string' ],
            [ 'field' => 'title', 'title' => 'Tytuł utworu', 'type' => 'string' ],
            [ 'field' => 'album', 'title' => 'Album', 'type' => 'string' ],
            [ 'field' => 'trackNumber', 'title' => 'Nr ścieżki', 'type' => 'string' ],
            [ 'field' => 'publisher', 'title' => 'Wydawca', 'type' => 'string' ],
            [ 'field' => 'genre', 'title' => 'Gatunek', 'type' => 'string' ],
            [ 'field' => 'year', 'title' => 'Rok', 'type' => 'string' ],
            [ 'field' => 'initialKey', 'title' => 'Tonacja', 'type' => 'string' ],
            [ 'field' => 'bpm', 'title' => 'BPM', 'type' => 'string' ],
        ];
    }

    /**
     * @todo Przenieść do innej klasy
     *
     * @return string[][]
     */
    private static function getTrackOptions(): array
    {
        return [
            // [ 'option' => 'remove-other-tags', 'title' => 'Usuń pozostałe metadane zapisane w pliku' ],
            [ 'option' => 'task:calculate-audio-data', 'title' => 'Oblicz tonację i BPM utworu' ],
        ];
    }

    private function getNotFoundRedirect(Response $response, string $pathname): ResponseInterface
    {
        $route = Route::create('search.simple.index')->withQuery([ 'name' => str_replace('-', ' ', $pathname) ]);
        $redirectUrl = $this->routeResolver->resolve($route);

        return $response->withRedirect($redirectUrl);
    }
}
