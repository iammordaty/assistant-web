<?php

namespace Assistant\Module\Mix\Controller;

use Assistant\Module\Mix\Extension\MixService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class MixController
{
    public function __construct(private MixService $mixService, private Twig $view)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        if ($request->getMethod() === 'POST') { // może to powinno zostać rozdzielone?
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
