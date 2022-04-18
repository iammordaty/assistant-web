<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common\Extension\Config;
use Cocur\BackgroundProcess\BackgroundProcess;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class TaskController
{
    private readonly string $baseDir;

    public function __construct(Config $config)
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

    public function move(ServerRequest $request, Response $response): ResponseInterface
    {
        $post = $request->getParsedBody();

        return $response
            ->withStatus(StatusCodeInterface::STATUS_IM_A_TEAPOT)
            ->withJson([
                'message' => 'not implemented (yet)',
                'post' => $post,
            ]);
    }
}
