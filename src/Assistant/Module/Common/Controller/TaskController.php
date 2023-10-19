<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Cocur\BackgroundProcess\BackgroundProcess;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final readonly class TaskController
{
    private string $baseDir;

    public function __construct(private RouteResolver $routeResolver, Config $config)
    {
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
        $command = sprintf('php %s/bin/console.php track:remove-metadata "%s"', $this->baseDir, $pathname);

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
        $markAsReady = $request->getParsedBodyParam('mark_as_ready') ? '--mark-as-ready' : '';

        foreach ($collectionItems as $pathname) {
            $command = sprintf(
                'php %s/bin/console.php track:rename %s --format="%s" "%s"',
                $this->baseDir,
                $markAsReady,
                $format,
                $pathname
            );

            shell_exec($command);
        }

        $route = Route::create('directory.browse.incoming');
        $redirectUrl = $this->routeResolver->resolve($route);

        return $response->withRedirect($redirectUrl);
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
