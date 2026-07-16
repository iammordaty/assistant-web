<?php

namespace Assistant\Module\Track\Controller\IncomingTrack;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\BeatportTrackMetadataSuggestionsService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Extension\UpdateTrackCommand;
use Cocur\BackgroundProcess\BackgroundProcess;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final class EditController
{
    public function __construct(
        private Id3Adapter $id3Adapter,
        private RouteResolver $routeResolver,
        private TrackService $trackService,
        private BeatportTrackMetadataSuggestionsService $trackMetadataSuggestions,
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

        $updateCommand = UpdateTrackCommand::fromRequest($request);

        $this
            ->id3Adapter
            ->setFile($track->getFile());

        // @todo: try...catch i wyświetlenie ew. wyjątku na froncie
        try {
            $this->id3Adapter->writeMetadata($updateCommand->toMetadata());
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($this->id3Adapter->getWriterErrors());
            var_dump($this->id3Adapter->getWriterWarnings());
            exit;
        }

        if ($updateCommand->calculateAudioData) {
            $command = sprintf(
                'php /data/bin/console.php track:calculate-audio-data -w "%s"',
                $track->getFile()->getPathname()
            );

            (new BackgroundProcess($command))->run();
        }

        $route = Route::create('incoming-track.edit.edit')
            ->withParams([ 'pathname' => $track->getFile()->getPathname() ]);

        $redirectUrl = $this->routeResolver->resolve($route);

        return $response->withRedirect($redirectUrl);
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
