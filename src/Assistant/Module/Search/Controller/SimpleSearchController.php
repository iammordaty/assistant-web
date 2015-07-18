<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Search\Controller as BaseController;
use Assistant\Module\Track;

use Cocur\Slugify\Slugify;

class SimpleSearchController extends BaseController
{
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
    
    protected function isRequestValid($criteria)
    {
        return !empty($criteria);
    }

    protected function getResults(array $criteria, array $options)
    {
        $repository = new Track\Repository\TrackRepository($this->app->container['db']);

        $results = [
            'tracks' => $repository->findBy($criteria, [ ], $options),
            'count' => $repository->count($criteria),
        ];

        return $results;
    }

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

    protected function getType()
    {
        return 'simple';
    }

    protected function handleRequest($form, $results, $paginator)
    {
        if ($this->getType() === 'simple' && $results['count'] === 1) {
            return $this->app->redirect(
                $this->app->urlFor('track.track.index', [ 'guid' => $results['tracks'][0]->guid])
            );
        }

        return parent::handleRequest($form, $results, $paginator);
    }
}
