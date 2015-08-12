<?php

namespace Assistant\Module\Dashboard\Repository;

use Assistant\Module\Common\Repository\Repository;

/**
 * Repozytorium wykorzystywane w dashboard
 *
 * @todo Przenieść do modułu Statistics
 */
class DashboardRepository extends Repository
{
    /**
     * {@inheritDoc}
     */
    protected static $collection = 'tracks';

    /**
     * Zwraca liczbę utworów w podziale na gatunki
     *
     * @param array $sort
     * @param integer $limit
     * @return array
     */
    public function getTrackCountByGenre(array $sort = [], $limit = 10)
    {
        return $this->aggregateBy('genre', 'string', $sort, $limit);
    }

    /**
     * Zwraca liczbę utworów w podziale na artystów
     *
     * @param array $sort
     * @param integer $limit
     * @return array
     */
    public function getTrackCountByArtist(array $sort = [], $limit = 10)
    {
        return $this->aggregateBy('artists', 'array', $sort, $limit);
    }

    /**
     * Agreguje dokumenty w bazie danych na podstawie przekazanych parametrów
     *
     * @param string $field
     * @param string $fieldType
     * @param array $sort
     * @param integer $limit
     * @return array
     */
    private function aggregateBy($field, $fieldType, array $sort = [], $limit = 10)
    {
        if (empty($sort)) {
            $sort = [ 'count' => -1 ];
        }

        $ops = [
            [
                '$group' => [
                    '_id' => [ $field => '$' . $field ],
                    'count' => [ '$sum' => 1 ],
                ],
            ],
            [
                '$sort' => $sort,
            ],
            [
                '$limit' => $limit,
            ],
        ];

        if ($fieldType === 'array') {
            array_unshift($ops, [ '$unwind' => '$' . $field ]);
        }

        $rawData = $this->db
            ->selectCollection(static::$collection)
            ->aggregate($ops);

        $result = [];

        foreach ($rawData['result'] as $group) {
            $result[$group['_id'][$field]] = $group['count'];
        }

        unset($ops, $rawData);

        return $result;
    }
}
