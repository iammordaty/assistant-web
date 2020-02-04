<?php

namespace Assistant\Module\Stats\Repository;

use Assistant\Module\Common\Repository\AbstractRepository;
use MongoDB\Driver\Cursor;

/**
 * Repozytorium zawierające metody statystyczne
 */
class StatsRepository extends AbstractRepository
{
    /**
     * {@inheritDoc}
     */
    protected const COLLECTION = 'tracks';

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
     * @param int $limit
     * @return Cursor
     */
    public function getRecentlyAddedTracks(int $limit = 10)
    {
        return $this->findBy([ ], [ 'limit' => $limit, 'sort' => [ 'indexed_date' => -1 ] ]);
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

        $rawData = $this->aggregate($ops);

        $result = [];

        foreach ($rawData as $group) {
            $result[$group['_id'][$field]] = $group['count'];
        }

        return $result;
    }
}
