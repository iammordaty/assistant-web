<?php

namespace Assistant\Module\Collection\Controller;

use Assistant\Module\Collection\Extension\Analysis\AnalysisViewType;
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
        return $this->view->render($response, '@collection/status/index.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getOverviewSummary(),
        ]);
    }

    public function crossReference(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/cross_reference.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::CROSS_REFERENCE,
            'crossReference' => $this->analysisService->getCrossReference(),
        ]);
    }

    public function filenameMismatch(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/filename_mismatch.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::FILENAME_MISMATCH,
            'issues' => $this->analysisService->getFilenameMismatchIssues(),
        ]);
    }

    public function emptyMetadata(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/single_track.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::EMPTY_METADATA,
            'issues' => $this->analysisService->getEmptyMetadataIssues(),
        ]);
    }

    public function lowAudioQuality(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/single_track.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::LOW_AUDIO_QUALITY,
            'issues' => $this->analysisService->getLowAudioQualityIssues(),
        ]);
    }

    public function similarArtist(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/similarity.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::SIMILAR_ARTIST,
            'issues' => $this->analysisService->getSimilarArtistIssues(),
        ]);
    }

    public function similarPublisher(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/similarity.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::SIMILAR_PUBLISHER,
            'issues' => $this->analysisService->getSimilarPublisherIssues(),
        ]);
    }

    public function similarGenre(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/similarity.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::SIMILAR_GENRE,
            'issues' => $this->analysisService->getSimilarGenreIssues(),
        ]);
    }

    public function suspiciousYear(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/single_track.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::SUSPICIOUS_YEAR,
            'issues' => $this->analysisService->getSuspiciousYearIssues(),
        ]);
    }

    public function rareGenre(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/rare.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::RARE_GENRE,
            'issues' => $this->analysisService->getRareGenreIssues(),
        ]);
    }

    public function rareKey(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/rare.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::RARE_KEY,
            'issues' => $this->analysisService->getRareKeyIssues(),
        ]);
    }

    public function potentialDuplicate(ServerRequest $request, Response $response): ResponseInterface
    {
        return $this->view->render($response, '@collection/status/types/potential_duplicate.twig', [
            'menu' => 'collection-status',
            'summary' => $this->analysisService->getSummary(),
            'viewType' => AnalysisViewType::POTENTIAL_DUPLICATE,
            'issues' => $this->analysisService->getPotentialDuplicateIssues(),
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
}
