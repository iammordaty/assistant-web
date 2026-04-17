<?php

namespace Assistant\Module\Collection\Controller;

use Assistant\Module\Collection\Extension\Analysis\AnalysisViewType;
use Assistant\Module\Collection\Extension\Analysis\CollectionAnalysisService;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final readonly class CollectionStatusController
{
    public function __construct(
        private CollectionAnalysisService $analysisService,
        private RouteResolver $routeResolver,
        private Twig $view,
    ) {
    }

    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $data = $this->analysisService->getOverviewData();

        return $this->view->render($response, '@collection/status/index.twig', [
            'menu' => 'collection-status',
            ...($data ?? ['summary' => null]),
        ]);
    }

    public function detail(ServerRequest $request, Response $response): ResponseInterface
    {
        $viewType = AnalysisViewType::tryFrom($request->getAttribute('type'));

        if (!$viewType) {
            $redirectUrl = $this->routeResolver->resolve(
                Route::create('collection.status.index'),
            );

            return $response->withRedirect($redirectUrl);
        }

        $summary = $this->analysisService->getSummary();
        $viewData = $this->analysisService->getViewData($viewType);
        $template = $this->resolveDetailTemplate($viewType);

        return $this->view->render($response, $template, [
            'menu' => 'collection-status',
            'summary' => $summary,
            'viewType' => $viewType,
            ...$viewData,
        ]);
    }

    public function toggleIgnore(ServerRequest $request, Response $response): ResponseInterface
    {
        $hash = $request->getAttribute('hash');

        if (!$hash) {
            return $response->withStatus(400)->withJson(['error' => 'Missing hash parameter']);
        }

        $isNowIgnored = $this->analysisService->toggleIgnore($hash);

        return $response->withJson(['ignored' => $isNowIgnored]);
    }

    private function resolveDetailTemplate(AnalysisViewType $viewType): string
    {
        return match ($viewType) {
            AnalysisViewType::CROSS_REFERENCE => '@collection/status/types/cross_reference.twig',
            AnalysisViewType::FILENAME_MISMATCH => '@collection/status/types/filename_mismatch.twig',
            AnalysisViewType::EMPTY_METADATA,
            AnalysisViewType::LOW_AUDIO_QUALITY,
            AnalysisViewType::SUSPICIOUS_YEAR => '@collection/status/types/single_track.twig',
            AnalysisViewType::SIMILAR_ARTIST,
            AnalysisViewType::SIMILAR_PUBLISHER,
            AnalysisViewType::SIMILAR_GENRE => '@collection/status/types/similarity.twig',
            AnalysisViewType::RARE_GENRE,
            AnalysisViewType::RARE_KEY => '@collection/status/types/rare.twig',
            AnalysisViewType::POTENTIAL_DUPLICATE => '@collection/status/types/potential_duplicate.twig',
        };
    }
}
