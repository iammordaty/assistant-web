<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Collection\Extension\Autocomplete\TrackAutocompleteService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AutocompleteController
{
    public function __construct(private TrackAutocompleteService $trackAutocompleteService)
    {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams()['query'] ?? null;
        $results = ($this->trackAutocompleteService)(trim($query));

        $response->getBody()->write(json_encode($results));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
