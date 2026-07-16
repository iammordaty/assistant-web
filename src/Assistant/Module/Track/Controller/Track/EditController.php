<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Extension\TrackUpdateService;
use Assistant\Module\Track\Extension\UpdateTrackCommand;
use Cocur\BackgroundProcess\BackgroundProcess;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final class EditController
{
    public function __construct(
        private Config $config,
        private Id3Adapter $id3Adapter,
        private RouteResolver $routeResolver,
        private TrackService $trackService,
        private TrackUpdateService $trackUpdateService,
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

        $updateCommand = UpdateTrackCommand::fromRequest($request);

        // @todo: B8 - zamienić var_dump/exit na log + flash + PRG redirect
        try {
            $result = $this->trackUpdateService->update($track, $updateCommand);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($this->id3Adapter->getWriterErrors());
            var_dump($this->id3Adapter->getWriterWarnings());
            exit;
        }

        $track = $result->track;
        $trackPathname = $track->getPathname();

        if ($updateCommand->calculateAudioData) {
            $command = sprintf(
                'php /data/bin/console.php track:calculate-audio-data -w "%s"',
                $track->getFile()->getPathname()
            );

            (new BackgroundProcess($command))->run();
        }

        foreach ($result->leftoverPaths as $leftoverPath) {
            $command = sprintf(
                'php %s/bin/console.php collection:clean "%s"',
                $this->config->get('base_dir'),
                $leftoverPath
            );

            shell_exec($command);
        }

        foreach ($result->createdPaths as $createdPath) {
            $command = sprintf(
                'php /data/bin/console.php collection:index -i pathname "%s"',
                $createdPath
            );

            shell_exec($command);
        }

        $command = sprintf(
            'php /data/bin/console.php collection:index -i pathname "%s"',
            $trackPathname
        );

        shell_exec($command);

        // jeśli zmieniła się nazwa artysty lub tytuł utworu to zmienił się także guid
        // dlatego pobieramy utwór raz jeszcze, na podstawie ścieżki aby móc przekierować na nowy guid
        $track = $this->trackService->getByPathname($trackPathname);

        $route = Route::create('track.track.index')->withParams([ 'guid' => $track->getGuid() ]);
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
