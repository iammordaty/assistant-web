<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Collection\Extension\Autocomplete\MetadataFieldAutocompleteService;
use InvalidArgumentException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final readonly class AutocompleteController
{
    public function __construct(private MetadataFieldAutocompleteService $autocompleteService)
    {
    }

    public function __invoke(ServerRequest $request, Response $response): Response
    {
        $query = ltrim($request->getQueryParam('query'));
        $type = $request->getQueryParam('type');

        try {
            $results = ($this->autocompleteService)($query, $type);
        } catch (InvalidArgumentException $e) {
            return $response
                ->withJson([ 'code' => $e->getCode(), 'message' => $e->getMessage() ])
                ->withStatus($e->getCode());
        }

        return $response->withJson($results);
    }
}
