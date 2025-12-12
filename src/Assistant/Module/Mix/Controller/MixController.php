<?php

namespace Assistant\Module\Mix\Controller;

use Assistant\Module\Mix\Extension\Mix\AttemptSaveRequest;
use Assistant\Module\Mix\Extension\Mix\MixPropertiesData;
use Assistant\Module\Mix\Extension\Mix\MixSaveRequest;
use Assistant\Module\Mix\Extension\MixService;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;
use Throwable;

final class MixController
{
    public function __construct(private MixService $mixService, private Twig $view)
    {
    }

    public function create(ServerRequest $request, Response $response): ResponseInterface
    {
        $mix = $this->mixService->createNewMix();

        return $this->view->render($response, '@mix/mix.twig', [
            'menu' => 'mix',
            'mix' => $mix->toDto()->toJson(),
        ]);
    }

    public function view(ServerRequest $request, Response $response): ResponseInterface
    {
        $guid = $request->getAttribute('guid');
        $mix = $this->mixService->getByGuid($guid);

        if (!$mix) {
            return $response
                ->withJson([ 'message' => sprintf('Mix "%s" does not exist.', $guid)])
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        if ($request->getQueryParam('dump') !== null) {
            d($mix, $mix->toDto()->toJson());
        }

        return $this->view->render($response, '@mix/mix.twig', [
            'menu' => 'mix',
            'mix' => $mix->toDto()->toJson(),
        ]);
    }

    public function saveMix(ServerRequest $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');

        try {
            $mixRequest = MixSaveRequest::create($request);

            $mix = null;

            if ($guid) {
                $mix = $this->mixService->getByGuid($guid);

                if (!$mix) {
                    return $response
                        ->withJson([ 'message' => sprintf('Mix "%s" does not exist.', $guid)])
                        ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
                }
            }

            $savedMix = $this->mixService->saveMix($mix, $mixRequest);
            
            return $response->withJson($savedMix->toDto()->toJson());
        } catch (InvalidArgumentException $e) {
            return $response
                ->withJson([ 'message' => $e->getMessage() ])
                ->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
        } catch (Throwable $e) {
            $body = [
                'message' => $e->getMessage(),
                'file' => pathinfo($e->getFile(), PATHINFO_FILENAME) . ':' . $e->getLine(),
            ];

            return $response
                ->withJson($body)
                ->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    public function saveAttempt(ServerRequest $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');

        try {
            $attemptRequest = AttemptSaveRequest::create($request);

            $mix = $this->mixService->getByGuid($guid);

            if (!$mix) {
                return $response
                    ->withJson([ 'message' => sprintf('Mix "%s" does not exist.', $guid)])
                    ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            }

            $savedMix = $this->mixService->saveAttempt($mix, $attemptRequest);
            
            return $response->withJson($savedMix->toDto()->toJson());
        } catch (InvalidArgumentException $e) {
            return $response
                ->withJson([ 'message' => $e->getMessage() ])
                ->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
        } catch (Throwable $e) {
            $body = [
                'message' => $e->getMessage(),
                'file' => pathinfo($e->getFile(), PATHINFO_FILENAME) . ':' . $e->getLine(),
            ];

            return $response
                ->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR)
                ->withJson($body);
        }
    }

    public function deleteMix(ServerRequest $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');

        if (!$guid) {
            return $response
                ->withJson([ 'message' => 'Invalid mix identifier' ])
                ->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        try {
            $mix = $this->mixService->getByGuid($guid);

            if (!$mix) {
                return $response
                    ->withJson([ 'message' => sprintf('Mix "%s" does not exist.', $guid)])
                    ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            }

            $result = $this->mixService->deleteMix($mix);
            
            if (!$result) {
                return $response
                    ->withJson([ 'message' => 'Failed to delete mix' ])
                    ->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            
            return $response->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
        } catch (Throwable $e) {
            $body = [
                'message' => $e->getMessage(),
                'file' => pathinfo($e->getFile(), PATHINFO_FILENAME) . ':' . $e->getLine(),
            ];

            return $response
                ->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR)
                ->withJson($body);
        }
    }
}
