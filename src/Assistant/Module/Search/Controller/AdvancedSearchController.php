<?php

namespace Assistant\Module\Search\Controller;

class AdvancedSearchController extends SimpleSearchController
{
    protected function getQueryCriteria()
    {
        $request = $this->app->request();

        $criteria = [];

        if (!empty($request->get('artist'))) {
            $criteria['artist'] = new \MongoRegex('/' . trim($request->get('artist')) . '/i');
        }

        if (!empty($request->get('title'))) {
            $criteria['title'] = new \MongoRegex('/' . trim($request->get('title')) . '/i');
        }

        if (!empty($request->get('genre'))) {
            $keys = array_map(
                function ($genre) {
                    return new \MongoRegex('/^' . trim($genre) . '$/i');
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

        if (!empty($request->get('year')) && ($year = (int) $request->get('year')) > 0) {
            $tolerance = (int) $request->get('year_tolerance');

            if ($tolerance === 0) {
                $criteria['year'] = $year;
            } else {
                $criteria['year'] = [ '$in' => range($year - $tolerance, $year + $tolerance) ];
            }
        }

        if (!empty($request->get('initial_key'))) {
            $keys = array_map(
                function ($key) {
                    return trim(strtoupper($key));
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

        if (!empty($request->get('bpm')) && ($bpm = (int) $request->get('bpm')) > 0) {
            $tolerance = (int) $request->get('bpm_tolerance');

            if ($tolerance === 0) {
                $criteria['bpm'] = $bpm;
            } else {
                $criteria['bpm'] = [ '$in' => range($bpm - $tolerance, $bpm + $tolerance) ];
            }
        }

        return $criteria;
    }

    protected function isRequestValid($criteria)
    {
        return !empty($criteria) || filter_input(INPUT_GET, 'submit', FILTER_VALIDATE_BOOLEAN) === true;
    }

    protected function getType()
    {
        return 'advanced';
    }
}
