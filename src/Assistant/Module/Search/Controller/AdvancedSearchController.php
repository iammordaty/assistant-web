<?php

namespace Assistant\Module\Search\Controller;

use Assistant\Module\Search\Extension\DateTimeMinMaxExpressionParser;
use Assistant\Module\Search\Extension\NumberMinMaxExpressionParser;
use Assistant\Module\Search\Extension\MinMaxExpressionInfoToDbQuery;
use Assistant\Module\Search\Extension\YearMinMaxExpressionParser;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

/**
 * Kontroler pozwalający na wyszukiwanie utworów po metadanych
 */
class AdvancedSearchController extends SimpleSearchController
{
    /**
     * {@inheritDoc}
     */
    protected const SEARCH_FORM_TYPE = 'advanced';

    /**
     * {@inheritDoc}
     */
    protected function getQueryCriteria()
    {
        $request = $this->app->request();

        $criteria = [];

        if (!empty($request->get('artist'))) {
            $criteria['artist'] = new Regex(trim($request->get('artist')), 'i');
        }

        if (!empty($request->get('title'))) {
            $criteria['title'] = new Regex(trim($request->get('title')), 'i');
        }

        if (!empty($request->get('genre'))) {
            $keys = array_map(
                function ($genre) {
                    return new Regex('^' . trim($genre) . '$', 'i');
                },
                explode(',', $request->get('genre'))
            );

            $filtered = array_merge(array_unique(array_filter($keys)));

            if (count($filtered) === 1) {
                $criteria['genre'] = $filtered[0];
            } else {
                $criteria['genre'] = [ '$in' => $filtered ];
            }
        }

        if (($publisher = $request->get('publisher'))) {
            $keys = array_map(
                function ($publisher) {
                    return new Regex('^' . trim($publisher) . '$', 'i');
                },
                explode(',', $publisher)
            );

            $filtered = array_merge(array_unique(array_filter($keys)));

            if (count($filtered) === 1) {
                $criteria['publisher'] = $filtered[0];
            } else {
                $criteria['publisher'] = [ '$in' => $filtered ];
            }
        }

        if (($year = $request->get('year'))) {
            $minMaxInfo = YearMinMaxExpressionParser::parse($year);

            if ($minMaxInfo) {
                $criteria['year'] = MinMaxExpressionInfoToDbQuery::convert($minMaxInfo);
            }
        }

        if (!empty($request->get('initial_key'))) {
            $keys = array_map(
                function ($key) {
                    return strtoupper(trim($key));
                },
                explode(',', $request->get('initial_key'))
            );

            $filtered = array_merge(array_unique(array_filter($keys)));

            if (count($filtered) === 1) {
                $criteria['initial_key'] = $filtered[0];
            } else {
                $criteria['initial_key'] = [ '$in' => $filtered ];
            }
        }

        if ($bpm = $request->get('bpm')) {
            $minMaxInfo = NumberMinMaxExpressionParser::parse($bpm);

            if ($minMaxInfo) {
                $criteria['bpm'] = MinMaxExpressionInfoToDbQuery::convert($minMaxInfo);
            }
        }

        if ($indexedDate = $request->get('indexed_date')) {
            $minMaxInfo = DateTimeMinMaxExpressionParser::parse($indexedDate);

            if ($minMaxInfo) {
                $criteria['indexed_date'] = MinMaxExpressionInfoToDbQuery::convert($minMaxInfo);
            }
        }

        return $criteria;
    }

    /**
     * {@inheritDoc}
     */
    protected function isRequestValid(array $criteria): bool
    {
        return !empty($criteria) || filter_input(INPUT_GET, 'submit', FILTER_VALIDATE_BOOLEAN) === true;
    }
}
