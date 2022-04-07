<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common\Extension\LogView;
use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaFactory;
use DateTime;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final class LogController
{
    private const MAX_ENTRIES_PER_PAGE = 200;
    private const MAX_ENTRIES_ON_REFRESH = 5;

    public function __construct(private LogView $logView, private Twig $view)
    {
    }

    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $page = $request->getQueryParam('page', 1);

        [ 'count' => $count, 'log' => $log ] = $this->logView->getLog(page: $page, limit: self::MAX_ENTRIES_PER_PAGE);

        $pagerfanta = PagerfantaFactory::createWithNullAdapter($count, $page, self::MAX_ENTRIES_PER_PAGE);

        return $this->view->render($response, '@common/log/index.twig', [
            'menu' => 'log',
            'autoRefresh' => $page === 1,
            'log' => $log,
            'pager' => $pagerfanta,
        ]);
    }

    public function refresh(ServerRequest $request, Response $response): ResponseInterface
    {
        $fromDate = DateTime::createFromFormat(
            DateTimeInterface::RFC3339_EXTENDED,
            $request->getQueryParam('fromDate')
        );
        $limit = $request->getQueryParam('limit', self::MAX_ENTRIES_ON_REFRESH);

        [ 'log' => $log ] = $this->logView->getLog(fromDate: $fromDate, limit: $limit);

        return $this->view->render($response, '@common/log/view.twig', [
            'autoRefresh' => true,
            'log' => $log,
        ]);
    }
}
