<?php

namespace Assistant\Module\Collection\Extension\Autocomplete;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Search\Extension\Criteria\Regex;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Result\TrackSearchResult;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Model\Track;

final class TrackAutocompleteService
{
    public function __construct(
        private RouteResolver $routeResolver,
        private SlugifyService $slugifyService,
        private TrackSearchService $searchService,
    ) {
    }

    /** @return TrackAutocompleteEntry[] */
    public function __invoke(string $name): array
    {
        if ($name === '' || str_contains($name, ': ')) {
            return [];
        }

        // krok 1: jeśli zwraca coś searchService to zwróć tylko to

        $criteria = SearchCriteriaFacade::createFromName($name);
        $result = $this->searchService->search($criteria, SearchSort::byTextScore(), limit: 20);

        if ($result->hasTracks()) {
            return $this->toJson($result);
        }

        // wrzucone sytuacyjnie, przemyśleć:
        // krok 2 (powyżej 2/3 znaków i jeśli powyższe nic nie zwróci): regex::startsWith
        // krok 3 (powyżej 4 znaków i jeśli powyższe nic nie zwróci): regex:contains

        // update 29.06.2021: na razie zostaje szukanie po indeksie tekstowym i guidzie. jeśli rezultaty nie będą ok,
        // trzeba zastanowić się nad wprowadzeniem powyższych kroków. należałoby wówczas zastanowić się
        // po jakim polu szukać powinny ww. regex-y: tylko guid? $or artystę i tytuł (w szczególności dla startsWith)?

        $slug = $this->slugifyService->slugify($name);

        if (strlen($slug) <= 2) {
            return [];
        }

        $criteria = SearchCriteriaFacade::createFromGuid(Regex::contains($slug));
        $result = $this->searchService->search($criteria, SearchSort::byName());

        if (!$result->hasTracks()) {
            return [];
        }

        return $this->toJson($result);
    }

    /** @return TrackAutocompleteEntry[] */
    private function toJson(TrackSearchResult $trackSearchResult): array
    {
        $createEntry = function (Track $track): TrackAutocompleteEntry {
            $route = Route::create('track.track.index', [ 'guid' => $track->getGuid() ]);
            $url = $this->routeResolver->resolve($route);

            return new TrackAutocompleteEntry(
                $track->getGuid(),
                $track->getArtists(),
                $track->getTitle(),
                $track->getName(),
                $track->getGenre(),
                $track->getLength(),
                $track->getBpm(),
                $track->getInitialKey(),
                $url,
            );
        };

        $entries = array_map(
            fn (Track $track) => $createEntry($track),
            iterator_to_array($trackSearchResult->tracks)
        );

        return $entries;
    }
}
