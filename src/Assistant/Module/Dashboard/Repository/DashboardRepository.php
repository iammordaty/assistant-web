<?php

namespace Assistant\Module\Dashboard\Repository;

use Assistant\Repository as BaseRepository;

class DashboardRepository extends BaseRepository
{
    protected static $collection = 'tracks';
    protected static $model = 'Assistant\Module\Track\Model\Track';
    protected static $baseConditions = [];

    public function getTrackCountByGenre(array $sort = [], $limit = 10)
    {
        return $this->aggregateBy('genre', 'string', $sort, $limit);
    }

    public function getTrackCountByArtist(array $sort = [], $limit = 10)
    {
        return $this->aggregateBy('artists', 'array', $sort, $limit);
    }

    protected function aggregateBy($field, $fieldType, array $sort = [], $limit = 10)
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
