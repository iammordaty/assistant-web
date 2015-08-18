<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Search\AbstractSearchController;
use Assistant\Module\Track;

use Cocur\Slugify\Slugify;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po nazwie lub artyście
 */
class SimpleSearchController extends AbstractSearchController
{
    /**
     * {@inheritDoc}
     */
    const SEARCH_FORM_TYPE = 'simple';

    /**
     * {@inheritDoc}
     */
    protected function getQueryCriteria()
    {
        $request = $this->app->request();

        $criteria = [];

        if (!empty($request->get('query'))) {
            $query = new \MongoRegex('/' . trim($request->get('query')) . '/i');
            $guidQuery = new \MongoRegex('/' . trim((new Slugify())->slugify($request->get('query'))) . '/i');

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
    protected function isRequestValid($criteria)
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
            'tracks' => $repository->findBy($criteria, [ ], $options),
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
