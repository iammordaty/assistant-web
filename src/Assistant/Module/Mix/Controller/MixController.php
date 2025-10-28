<?php

namespace Assistant\Module\Mix\Controller;

use Assistant\Module\Mix\Extension\Mix\AttemptSaveRequest;
use Assistant\Module\Mix\Extension\Mix\MixSaveRequest;
use Assistant\Module\Mix\Extension\MixService;
use Assistant\Module\Mix\Model\AttemptDto;
use Assistant\Module\Mix\Model\Mix;
use Assistant\Module\Mix\Model\MixDto;
use DateTime;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Views\Twig;

final class MixController
{
    public function __construct(private MixService $mixService, private Twig $view)
    {
    }

    public function index(ServerRequest $request, Response $response): ResponseInterface
    {
        $guid = $request->getAttribute('guid');
        $mix = $this->mixService->getByGuid($guid);

        if (isset($request->getQueryParams()['dump'])) {
            /** @noinspection DebugFunctionUsageInspection */
            dump($mix, $mix->toDto()->toJson());
        }

        return $this->view->render($response, '@mix/mix.twig', [
            'menu' => 'mix',
            'mix' => $mix->toDto()->toJson(),
        ]);
    }

    /* @fixme: wygenerowane przez AI */
    public function saveMixProperties(ServerRequest $request, Response $response): Response
    {
        try {
            $guid = $request->getAttribute('guid');
            $mix = $this->mixService->getByGuid($guid);

            if (!$mix) {
                return $response->withJson([ 'error' => 'Mix not found' ], 404);
            }

            $mixRequest = MixSaveRequest::create($request);
            $mixDto = $mix->toDto();

            $created = $mixRequest->created ? new DateTime($mixRequest->created) : $mixDto->created;
            $modified = new DateTime();
            $performed = $mixRequest->performed ? new DateTime($mixRequest->performed) : $mixDto->performed;

            $updatedMixDto = new MixDto(
                $mixDto->guid,
                $mixRequest->name,
                $mixRequest->description,
                $created,
                $modified,
                $performed,
                $mixRequest->comment,
                $mixDto->attempts
            );

            $mix = $this->mixService->save(Mix::fromDto($updatedMixDto));

            return $response->withJson($mix->toDto()->toJson());    
        } catch (InvalidArgumentException $e) {
            return $response->withJson([ 'error' => $e->getMessage() ], 400);
        }
    }

    /* @fixme: wygenerowane przez AI */
    public function saveAttempt(ServerRequest $request, Response $response): Response
    {
        try {
            $guid = $request->getAttribute('guid');
            $mix = $this->mixService->getByGuid($guid);

            if (!$mix) {
                return $response->withJson([ 'error' => 'Mix not found' ], 404);
            }

            $attemptRequest = AttemptSaveRequest::create($request);

            $mixDto = $mix->toDto();

            $attemptIndex = $attemptRequest->number - 1;

            if (!isset($mixDto->attempts[$attemptIndex])) {
                return $response->withJson([ 'error' => 'Attempt not found' ], 404);
            }

            $updatedAttempt = AttemptDto::fromRequest($attemptRequest);

            $attempts = $mixDto->attempts;
            $attempts[$attemptIndex] = $updatedAttempt;

            $updatedMixDto = new MixDto(
                $mixDto->guid,
                $mixDto->name,
                $mixDto->description,
                $mixDto->created,
                new DateTime(),
                $mixDto->performed,
                $mixDto->comment,
                $attempts
            );

            $mix = $this->mixService->save(Mix::fromDto($updatedMixDto));

            return $response->withJson($mix->toDto()->toJson());
        } catch (InvalidArgumentException $e) {
            return $response->withJson(['error' => $e->getMessage()], 400);
        }
    }
}
