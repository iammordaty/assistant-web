<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common\Extension\Config;
use Cocur\BackgroundProcess\BackgroundProcess;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TaskController
{
    private string $baseDir;

    public function __construct(Config $config)
    {
        $this->baseDir = $config->get('base_dir');
    }

    public function calculateAudioData(Request $request, Response $response): Response
    {
        $pathname = $request->getParsedBody()['pathname'] ?? null;
        $command = sprintf('php %s/bin/console.php track:calculate-audio-data -w "%s"', $this->baseDir, $pathname);

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        $response->getBody()->write(json_encode([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function move(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        $response->getBody()->write(json_encode([
            'message' => 'not-implemented :-(',
            'post' => $post,
        ]));

        return $response
            ->withStatus(418)
            ->withHeader('Content-Type', 'application/json');
    }
}
