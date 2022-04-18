<?php

namespace Assistant\Module\Mix\Controller;

use Assistant\Module\Mix\Extension\MixService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final class MixController
{
    public function __construct(private MixService $mixService, private Twig $view)
    {
    }

    public function index(ServerRequest $request, ResponseInterface $response): ResponseInterface
    {
        if ($request->isPost()) { // może to powinno zostać rozdzielone?
            $form = $request->getParsedBody();

            $listing = explode(PHP_EOL, $form['listing']);

            [ $mix, $similarityGrid ] = $this->mixService->getMixInfo($listing);
        }

        return $this->view->render($response, '@mix/index.twig', [
            'menu' => 'mix',
            'form' => $form ?? null,
            'similarityGrid' => $similarityGrid ?? null,
            'mix' => $mix ?? null,
        ]);
    }
}
