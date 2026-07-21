<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\Messages;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\FilenameFormat;
use Assistant\Module\Track\Extension\FilenameFormatSuggester;
use Assistant\Module\Track\Extension\TrackRenameService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Extension\TrackUpdateService;
use Assistant\Module\Track\Extension\UpdateTrackCommand;
use Assistant\Module\Track\Model\Track;
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
        private TrackRenameService $trackRenameService,
        private FilenameFormatSuggester $filenameFormatSuggester,
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
            'rename' => $this->getRenameData($track),
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Podgląd nazwy pliku dla bieżącej zawartości formularza - liczony przez ten sam kod, który
     * wykona zapis, żeby podstawienie pól, uzupełnienie numeru i sanityzacja nazwy nie musiały być
     * duplikowane w JS (i nie mogły się z nim po cichu rozjechać).
     */
    public function namePreview(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname');
        $track = $this->trackService->getByPathname($pathname);

        if (!$track) {
            return $response->withJson([ 'target' => null, 'error' => 'Nie znaleziono utworu.' ], 404);
        }

        $fixedBaseDir = $this->trackRenameService->getFixedBaseDir($track);

        try {
            $command = UpdateTrackCommand::fromRequest($request);

            // ta sama decyzja, którą podejmie zapis - inaczej podgląd pokazywałby co innego,
            // niż faktycznie się wydarzy (w szczególności w trybie ręcznym)
            $target = $this->trackUpdateService->resolveTargetFor($track, $command);
        } catch (\Throwable $e) {
            // niekompletne dane w formularzu są normalnym stanem w trakcie pisania - podgląd
            // pokazuje wtedy powód, zamiast wywracać żądanie
            return $response->withJson([ 'target' => null, 'error' => $e->getMessage() ]);
        }

        $pathname = $target?->getPathname() ?? $track->getFile()->getPathname();

        return $response->withJson([
            'target' => self::toFixedBaseRelative($fixedBaseDir, $pathname),
            'error' => null,
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

    /**
     * Dane pola wyboru nazwy pliku. Format jest jawnym wejściem zapisu, a rozpoznanie obecnego
     * układu służy wyłącznie do wstępnego zaznaczenia opcji - użytkownik może wybrać inną albo
     * wpisać nazwę ręcznie.
     */
    private function getRenameData(Track $track): array
    {
        $fixedBaseDir = $this->trackRenameService->getFixedBaseDir($track);

        $formats = array_map(
            static fn (FilenameFormat $format) => [
                'value' => $format->value,
                'label' => $format->label(),
                'description' => $format->description(),
            ],
            FilenameFormat::forCollection(),
        );

        return [
            'formats' => $formats,
            'suggested' => $this->filenameFormatSuggester->suggest($track)->value,
            'manual_choice' => UpdateTrackCommand::MANUAL_RENAME_CHOICE,
            'keep_choice' => UpdateTrackCommand::KEEP_NAME_CHOICE,
            'fixed_base_dir' => $fixedBaseDir,
            'current' => self::toFixedBaseRelative($fixedBaseDir, $track->getFile()->getPathname()),
        ];
    }

    /** Ścieżka względem niezmiennej części - tak nazwa jest pokazywana i tak jest przyjmowana z formularza */
    private static function toFixedBaseRelative(string $fixedBaseDir, string $pathname): string
    {
        $fixedBaseDir = rtrim($fixedBaseDir, '/');

        return str_starts_with($pathname, $fixedBaseDir . '/')
            ? substr($pathname, strlen($fixedBaseDir) + 1)
            : $pathname;
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
