<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Collection\Extension\Autocomplete\TrackAutocompleteService;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class AutocompleteController
{
    public function __construct(private readonly TrackAutocompleteService $trackAutocompleteService)
    {
    }

    public function __invoke(ServerRequest $request, Response $response): Response
    {
        $query = trim($request->getQueryParam('query'));
        $results = ($this->trackAutocompleteService)($query);

        return $response->withJson($results);
    }
}
