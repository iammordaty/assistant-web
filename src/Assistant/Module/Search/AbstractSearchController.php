<?php

namespace Assistant\Module\Search;

use Assistant\Module\Common\Controller\AbstractController;

/**
 * Bazowy kontroler wyszukiwania
 */
abstract class AbstractSearchController extends AbstractController
{
    /**
     * @var integer
     */
    const MAX_TRACKS_PER_PAGE = 50;

    public function index()
    {
        $request = $this->app->request();
        $criteria = $this->getQueryCriteria();

        $results = [ ];
        $paginator = null;

        if ($this->isRequestValid($criteria) === true) {
            $page = max(1, (int) $request->get('page', 1));

            $results = $this->getResults(
                $criteria,
                [
                    'skip' => ($page - 1) * static::MAX_TRACKS_PER_PAGE,
                    'limit' => static::MAX_TRACKS_PER_PAGE,
                    'sort' => $this->getSort()
                ]
            );

            if ($results['count'] > static::MAX_TRACKS_PER_PAGE) {
                $paginator = $this->getPaginator($page, $results['count']);
            }
        }

        $this->handleRequest($request->get(), $results, $paginator);
    }

    abstract protected function getQueryCriteria();

    abstract protected function isRequestValid($criteria);

    abstract protected function getResults(array $criteria, array $options);

    abstract protected function getPaginator($page, $count);

    abstract protected function getType();

    protected function handleRequest($form, $results, $paginator)
    {
        return $this->app->render(
            $this->getTemplateName(),
            [
                'menu' => 'search',
                'form' => $form,
                'result' => $results,
                'paginator' => $paginator,
            ]
        );
    }

    protected function getRouteName()
    {
        return sprintf('search.%s.index', strtolower($this->getType()));
    }

    protected function getTemplateName()
    {
        return sprintf('@search/%s/index.twig', strtolower($this->getType()));
    }

    protected function getSort()
    {
        return [ 'guid' => 1 ];
    }
}
