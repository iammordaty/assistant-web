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

    /**
     * Renderuje stronę wyszukiwania
     */
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

    /**
     * Zwraca kryteria zapytania
     *
     * @return array
     */
    abstract protected function getQueryCriteria();

    /**
     * Zwraca informację, czy żądanie jest prawidłowe
     *
     * @return bool
     */
    abstract protected function isRequestValid($criteria);

    /**
     * Zwraca utwory spełniające podane kryteria wyszukiwania
     *
     * @param array $criteria
     * @param array $options
     * @return array
     */
    abstract protected function getResults(array $criteria, array $options);

    /**
     * Zwraca obiekt paginatora lub null, jeśli paginator nie jest wymagany
     *
     * @see MAX_TRACKS_PER_PAGE
     * @param integer $page
     * @param integer $count
     * @return \Pagerfanta\Pagerfanta|null
     */
    abstract protected function getPaginator($page, $count);

    /**
     * Zwraca typ kontrolera obsługującego wyszukiwanie
     */
    abstract protected function getType();

    /**
     * Obsługuje żądanie
     *
     * @param array $form
     * @param array $results
     * @param \Pagerfanta\Pagerfanta|null $paginator
     */
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

    /**
     * Zwraca nazwę routingu służącego do nawigacji pomiędzy stronami
     *
     * @return string
     */
    protected function getRouteName()
    {
        return sprintf('search.%s.index', strtolower($this->getType()));
    }

    /**
     * Zwraca nazwę szablonu do wyrenderowania
     *
     * @return string
     */
    protected function getTemplateName()
    {
        return sprintf('@search/%s/index.twig', strtolower($this->getType()));
    }

    /**
     * Zwraca ustawienia sortowania
     *
     * @return array
     */
    protected function getSort()
    {
        return [ 'guid' => 1 ];
    }
}
