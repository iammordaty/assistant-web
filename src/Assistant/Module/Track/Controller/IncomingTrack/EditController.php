<?php

namespace Assistant\Module\Track\Controller\IncomingTrack;

use Assistant\Module\Common\Extension\Messages;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\BeatportTrackMetadataSuggestionsService;
use Assistant\Module\Track\Extension\TrackMetadataWriter;
use Assistant\Module\Track\Extension\TrackService;
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
        private BeatportTrackMetadataSuggestionsService $trackMetadataSuggestions,
        private TrackMetadataWriter $trackMetadataWriter,
        private Messages $messages,
        private Logger $logger,
        private Twig $view,
    ) {
    }

    public function edit(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->createFromFile($pathname);

        if (!$track) {
            return $this->getNotFoundRedirect($response, $pathname);
        }

        $query = $request->getQueryParam('query');

        if (!$query) {
            $query = pathinfo($track->getPathname(), PATHINFO_FILENAME);
        }

        $suggestions = $this->trackMetadataSuggestions->get($query);

        $route = Route::create('directory.browse.incoming')->withParams([ 'pathname' => $track->getFile()->getPath() ]);
        $returnUrl = $this->routeResolver->resolve($route);

        return $this->view->render($response, '@track/incomingTrack/edit/edit.twig', [
            'suggestions' => $suggestions,
            'menu' => 'track',
            'metadata' => [
                'fields' => self::getEditableMetadataFields(),
                'options' => self::getMetadataOptions(),
            ],
            'pathname' => $pathname,
            'query' => $query,
            'track' => $track,
            'return_url' => $returnUrl,
        ]);
    }

    public function save(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->createFromFile($pathname);

        if (!$track) {
            return $this->getNotFoundRedirect($response, $pathname);
        }

        $editUrl = $this->routeResolver->resolve(
            Route::create('incoming-track.edit.edit')->withParams([ 'pathname' => $track->getFile()->getPathname() ])
        );

        try {
            $updateCommand = UpdateTrackCommand::fromRequest($request);
            $warnings = $this->trackMetadataWriter->write($track->getFile(), $updateCommand->toMetadata());

            if ($updateCommand->calculateAudioData) {
                $this->trackMetadataWriter->calculateAudioData($track->getFile()->getPathname());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Incoming track update failed', [ 'pathname' => $pathname, 'error' => $e->getMessage() ]);
            $this->messages->addError($e->getMessage());

            return $response->withRedirect($editUrl);
        }

        $this->messages->addSuccess('Zapisano metadane utworu.');

        foreach ($warnings as $warning) {
            $this->messages->addWarning($warning);
        }

        return $response->withRedirect($editUrl);
    }

    /** @todo Przenieść do innej klasy */
    private static function getEditableMetadataFields(): array
    {
        return [
            [ 'field' => 'artist', 'title' => 'Wykonawca' ],
            [ 'field' => 'title', 'title' => 'Tytuł utworu' ],
            [ 'field' => 'album', 'title' => 'Album' ],
            [ 'field' => 'trackNumber', 'title' => 'Nr ścieżki' ],
            [ 'field' => 'publisher', 'title' => 'Wydawca' ],
            [ 'field' => 'genre', 'title' => 'Gatunek' ],
            [ 'field' => 'year', 'title' => 'Rok' ],
            [ 'field' => 'initialKey', 'title' => 'Tonacja' ],
            [ 'field' => 'bpm', 'title' => 'BPM' ],
        ];
    }

    /**
     * @todo Przenieść do innej klasy
     *
     * @return string[][]
     */
    private static function getMetadataOptions(): array
    {
        return [
            // [ 'option' => 'remove-other-tags', 'title' => 'Usuń pozostałe metadane zapisane w pliku' ],
            [ 'option' => 'task:calculate-audio-data', 'title' => 'Oblicz tonację i BPM utworu' ],
        ];
    }

    private function getNotFoundRedirect(Response $response, string $pathname): ResponseInterface
    {
        $route = Route::create('directory.browse.incoming');
        $redirectUrl = $this->routeResolver->resolve($route);

        return $response->withRedirect($redirectUrl);
    }
}
