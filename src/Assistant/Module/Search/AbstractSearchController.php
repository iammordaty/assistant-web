<?php

namespace Assistant\Module\Search;

use Assistant\Module\Common\Controller\AbstractController;

/**
 * Bazowy kontroler wyszukiwania
 * 
 * @todo: w pierwszym kroku zamienić dziedziczenie na kompozycję
 */
abstract class AbstractSearchController extends AbstractController
{
    /**
     * Maksymalna liczba wyszukanych utworów na stronie
     *
     * @var integer
     */
    protected const MAX_TRACKS_PER_PAGE = 50;

    /**
     * Typ wyszukiwarki
     */
    protected const SEARCH_FORM_TYPE = null;

    /**
     * Renderuje stronę wyszukiwania
     */
    public function index()
    {
        $request = $this->app->request();
        $criteria = $this->getQueryCriteria();

        $results = [ 'count' => 0 ];
        $paginator = null;

        if ($this->isRequestValid($criteria) === true) {
            $page = max(1, (int) $request->get('page', 1));

            $results = $this->getResults(
                $criteria,
                [
                    'skip' => ($page - 1) * static::MAX_TRACKS_PER_PAGE,
                    'limit' => static::MAX_TRACKS_PER_PAGE,
                    'sort' => $this->getSortParams()
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
     * @param array $criteria
     * @return bool
     */
    abstract protected function isRequestValid(array $criteria): bool;

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
     * Zwraca typ kontrolera obsługującego wyszukiwanie
     */
    protected function getSearchFormType()
    {
        return static::SEARCH_FORM_TYPE;
    }

    /**
     * Zwraca nazwę routingu służącego do nawigacji pomiędzy stronami
     *
     * @return string
     */
    protected function getRouteName()
    {
        return sprintf('search.%s.index', strtolower($this->getSearchFormType()));
    }

    /**
     * Zwraca nazwę szablonu do wyrenderowania
     *
     * @return string
     */
    protected function getTemplateName()
    {
        return sprintf('@search/%s/index.twig', strtolower($this->getSearchFormType()));
    }

    /**
     * Zwraca ustawienia sortowania
     *
     * @return array
     */
    protected function getSortParams()
    {
        return [ 'guid' => 1 ];
    }
}
