<?php

namespace Assistant\Module\Collection\Controller;

use Assistant\Module\Collection\Extension\Analysis\CollectionAnalysisService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final readonly class CollectionStatusController
{
    public function __construct(
        private CollectionAnalysisService $analysisService,
        private Twig $view,
    ) {
    }

    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $data = $this->analysisService->getAnalysisData();

        return $this->view->render($response, '@collection/status/index.twig', [
            'menu' => 'collection-status',
            ...($data ?? ['summary' => null]),
        ]);
    }

    public function toggleIgnore(ServerRequest $request, Response $response): ResponseInterface
    {
        $hash = $request->getParsedBodyParam('hash');

        if (!$hash) {
            return $response->withStatus(400)->withJson(['error' => 'Missing hash parameter']);
        }

        $isNowIgnored = $this->analysisService->toggleIgnore($hash);

        return $response->withJson(['ignored' => $isNowIgnored]);
    }
}
