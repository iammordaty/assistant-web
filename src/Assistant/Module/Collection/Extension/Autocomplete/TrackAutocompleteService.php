<?php

namespace Assistant\Module\Collection\Extension\Autocomplete;

use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Storage\Regex;
use Assistant\Module\Search\Extension\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\SearchSort;
use Assistant\Module\Search\Extension\TrackSearchService;
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
    public function __invoke(string $query): array
    {
        if ($query === '') {
            return [];
        }

        // krok 1: jeśli zwraca coś searchService to zwróć tylko to

        [ 'count' => $count, 'tracks' => $tracks ] = $this->searchService->findByName(
            $query,
            sort: SearchSort::TEXT_SCORE,
            page: 1
        );

        if ($count > 0) {
            return $this->toArray($tracks);
        }

        // wrzucone sytuacyjnie, przemyśleć:
        // krok 2 (powyżej 2/3 znaków i jeśli powyższe nic nie zwróci): regex::startsWith
        // krok 3 (powyżej 4 znaków i jeśli powyższe nic nie zwróci): regex:contains

        // update 29.06.2021: póki co zostaje szukanie po indeksie tekstowym i guidzie. jeśli rezultaty nie będą ok,
        // trzeba zastanowić się nad wprowadzeniem powyższych kroków. należałoby wówczas zastanowić się
        // po jakim polu szukać powinny ww. regex-y: tylko guid? $or artystę i tytuł (w szczególności dla startsWith)?

        $slug = $this->slugifyService->slugify($query);

        if (strlen($slug) <= 2) {
            return [];
        }

        $criteria = SearchCriteriaFacade::createFromGuid(Regex::contains($slug));
        $tracks = $this->searchService->findBy($criteria);

        return $this->toArray($tracks);
    }

    /** @return TrackAutocompleteEntry[] */
    private function toArray(iterable $tracks): array
    {
        $createEntry = function (Track $track): TrackAutocompleteEntry {
            $route = Route::create('track.track.index', [ 'guid' => $track->getGuid() ]);
            $url = $this->routeResolver->resolve($route);

            return new TrackAutocompleteEntry($track->getGuid(), $track->getName(), $url);
        };

        $entries = array_map(
            fn (Track $track) => $createEntry($track),
            iterator_to_array($tracks)
        );

        return $entries;
    }
}
