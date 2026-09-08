<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\ConsoleCommandRunner;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\DateDirectory;
use Cocur\BackgroundProcess\BackgroundProcess;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final readonly class TaskController
{
    private string $baseDir;

    public function __construct(
        private RouteResolver $routeResolver,
        private ConsoleCommandRunner $consoleCommandRunner,
        Config $config,
    ) {
        $this->baseDir = $config->get('base_dir');
    }

    public function calculateAudioData(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getParsedBodyParam('pathname');
        $command = sprintf('php %s/bin/console.php track:calculate-audio-data -w "%s"', $this->baseDir, $pathname);

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        return $response->withJson([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]);
    }

    public function removeMetadata(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getParsedBodyParam('pathname');
        $command = sprintf(
            'php %s/bin/console.php track:remove-metadata --keep-supported-fields "%s"',
            $this->baseDir,
            $pathname
        );

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        return $response->withJson([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]);
    }

    public function cleanPathname(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getParsedBodyParam('pathname');
        $command = sprintf('php %s/bin/console.php track:rename --clean "%s"', $this->baseDir, $pathname);

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        return $response->withJson([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]);
    }

    public function rename(ServerRequest $request, Response $response): ResponseInterface
    {
        $collectionItems = json_decode($request->getParsedBodyParam('elements'), true);
        $format = $request->getParsedBodyParam('format');

        $options = [];

        if ($request->getParsedBodyParam('mark_as_ready')) {
            $options[] = '--mark-as-ready';
        }

        if ($request->getParsedBodyParam('move_to_date_dir')) {
            $options[] = '--date-dir';

            $dateDir = DateDirectory::tryParse(sprintf(
                '%s/%s',
                trim((string) $request->getParsedBodyParam('date_dir_year')),
                trim((string) $request->getParsedBodyParam('date_dir_month')),
            ));

            if ($dateDir !== null) {
                $options[] = '--date-dir-value=' . $dateDir;
            }
        }

        foreach ($collectionItems as $pathname) {
            // każdy token escapowany przez runner - format i ścieżka pochodzą z formularza (B11)
            $command = $this->consoleCommandRunner->buildConsoleCommandLine([
                'track:rename',
                ...$options,
                '--format=' . $format,
                $pathname,
            ]);

            shell_exec($command);
        }

        $route = Route::create('directory.browse.incoming');
        $redirectUrl = $this->routeResolver->resolve($route);

        return $response->withRedirect($redirectUrl);
    }

    public function reindexSimilarTracksCollection(ServerRequest $request, Response $response): ResponseInterface
    {
        $command = sprintf('php %s/bin/console.php collection:reindex-similar-tracks', $this->baseDir);

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        return $response->withJson([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]);
    }

    public function remove(ServerRequest $request, Response $response): ResponseInterface
    {
        $collectionItems = json_decode($request->getParsedBodyParam('elements'), true);

        foreach ($collectionItems as $pathname) {
            unlink($pathname);
        }

        $route = Route::create('directory.browse.incoming');
        $redirectUrl = $this->routeResolver->resolve($route);

        return $response->withRedirect($redirectUrl);
    }
}
