<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Search\AbstractSearchController;
use Assistant\Module\Track;
use Cocur\Slugify\Slugify;
use MongoDB\BSON\Regex;

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
        $repository = new Track\Repository\TrackRepository($this->app->container['db']);

        $results = [
            'tracks' => $repository->findBy($criteria, $options),
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

        $paginator = new \Pagerfanta\Pagerfanta(new \Pagerfanta\Adapter\NullAdapter($totalCount));
        $paginator->setMaxPerPage(static::MAX_TRACKS_PER_PAGE);

        try {
            $paginator->setCurrentPage($pageNo);
        } catch (\Pagerfanta\Exception\NotValidCurrentPageException $e) {
            $paginator = null;

            unset($e);
        }

        if ($paginator === null) {
            return null;
        }

        return (new \Pagerfanta\View\TwitterBootstrap3View())->render(
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
            return $this->app->redirect(
                $this->app->urlFor('track.track.index', [ 'guid' => $results['tracks']->current()->guid])
            );
        }

        return parent::handleRequest($form, $results, $paginator);
    }
}
