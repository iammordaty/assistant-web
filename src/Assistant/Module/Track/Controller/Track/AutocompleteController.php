<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Collection\Extension\Autocomplete\TrackAutocompleteService;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final readonly class AutocompleteController
{
    public function __construct(private TrackAutocompleteService $trackAutocompleteService)
    {
    }

    public function __invoke(ServerRequest $request, Response $response): Response
    {
        $name = trim($request->getQueryParam('name'));
        $results = ($this->trackAutocompleteService)($name);

        return $response->withJson($results);
    }
}
