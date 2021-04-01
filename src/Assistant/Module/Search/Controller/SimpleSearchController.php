<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Search\AbstractSearchController;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Cocur\Slugify\Slugify;
use MongoDB\BSON\Regex;
use Pagerfanta\Adapter\NullAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\TwitterBootstrap3View;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po nazwie lub artyście
 */
class SimpleSearchController extends AbstractSearchController
{
    /**
     * {@inheritDoc}
     */
    protected const SEARCH_FORM_TYPE = 'simple';

    /**
     * {@inheritDoc}
     */
    protected function getQueryCriteria()
    {
        $criteria = [];

        $query = trim($this->app->request()->get('query'));

        if (!empty($query)) {
            $slugify = new Slugify();

            $query = new Regex($query, 'i');
            $guidQuery = new Regex($slugify->slugify($query), 'i');

            $criteria = [
                '$or' => [
                    [ 'artist' => $query ],
                    [ 'title' => $query ],
                    [ 'guid' => $guidQuery ],
                ]
            ];
        }

        return $criteria;
    }

    /**
     * {@inheritDoc}
     */
    protected function isRequestValid(array $criteria): bool
    {
        return !empty($criteria);
    }

    /**
     * {@inheritDoc}
     */
    protected function getResults(array $criteria, array $options)
    {
        $repository = $this->app->container[TrackRepository::class];

        [ 'sort' => $sort, 'limit' => $limit, 'skip' => $skip ] = $options; // przekazywać poziom wyżej

        $results = [
            'tracks' => iterator_to_array($repository->findBy($criteria, $sort, $limit, $skip)),
            'count' => $repository->count($criteria),
        ];

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPaginator($pageNo, $totalCount)
    {
        if ($totalCount <= static::MAX_TRACKS_PER_PAGE) {
            return null;
        }

        $paginator = new Pagerfanta(new NullAdapter($totalCount));
        $paginator->setMaxPerPage(static::MAX_TRACKS_PER_PAGE);

        try {
            $paginator->setCurrentPage($pageNo);
        } catch (NotValidCurrentPageException $e) {
            $paginator = null;

            unset($e);
        }

        if ($paginator === null) {
            return null;
        }

        return (new TwitterBootstrap3View())->render(
            $paginator,
            function($page) {
                return sprintf(
                    '%s?%s&page=%d',
                    $this->app->urlFor($this->getRouteName()),
                    http_build_query($this->app->request->get()),
                    $page
                );
            },
            [
                'proximity' => 2,
                'previous_message' => 'Poprzednia',
                'next_message' => 'Następna',
            ]
        );
    }
    
    /**
     * {@inheritDoc}
     */
    protected function handleRequest($form, $results, $paginator)
    {
        if ($this->getSearchFormType() === self::SEARCH_FORM_TYPE && $results['count'] === 1) {
            /** @var Track $track */
            $track = reset($results['tracks']);
            $redirectUrl = $this->app->urlFor('track.track.index', [ 'guid' => $track->getGuid() ]);

            return $this->app->redirect($redirectUrl);
        }

        return parent::handleRequest($form, $results, $paginator);
    }
}
