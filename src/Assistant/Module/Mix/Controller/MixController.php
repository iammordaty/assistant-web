<?php

namespace Assistant\Module\Mix\Controller;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Mix\Extension\MixService;
use Slim\Slim;

final class MixController extends AbstractController
{
    private MixService $mixService;

    public function __construct(Slim $app)
    {
        parent::__construct($app);

        $this->mixService = $app->container[MixService::class];
    }

    public function index()
    {
        $request = $this->app->request();

        if ($request->isPost()) {
            $listing = explode(PHP_EOL, $request->post('listing'));

            [ $mix, $matrix ] = $this->mixService->getMixInfo($listing);
        }

        return $this->app->render('@mix/index.twig', [
            'menu' => 'mix',
            'form' => $request->post(),
            'matrix' => $matrix ?? [],
            'mix' => $mix ?? [],
        ]);
    }
}
