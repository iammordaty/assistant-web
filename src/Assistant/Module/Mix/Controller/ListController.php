<?php

namespace Assistant\Module\Mix\Controller;

use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaFactory;
use Assistant\Module\Mix\Extension\MixService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final class ListController
{
    public function __construct(private MixService $mixService, private Twig $view)
    {
    }

    public function index(ServerRequest $request, ResponseInterface $response): ResponseInterface
    {
        $page = (int) ($request->getQueryParams()['page'] ?? 1);

        [ 'mixes' => $mixes, 'count' => $count ] = $this->mixService->getMixes($page);

        $paginator = PagerfantaFactory::createWithNullAdapter(
            $count,
            $page,
            MixService::MAX_MIXES_PER_PAGE,
        );

        return $this->view->render($response, '@mix/list.twig', [
            'menu' => 'mix',
            'mixes' => $mixes,
            'paginator' => $paginator,
        ]);
    }
}
