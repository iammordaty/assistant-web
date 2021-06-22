<?php

namespace Assistant\Module\Stats\Repository;

use Assistant\Module\Common\Storage\Storage;
use MongoDB\Database;

/**
 * Repozytorium zawierające metody statystyczne
 */
final class StatsRepository
{
    private const COLLECTION_NAME = 'tracks';

    private const FIELD_TYPE_ARRAY = 'array';

    private const FIELD_TYPE_STRING = 'string';

    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public static function factory(Database $database): self
    {
        $collection = $database->selectCollection(self::COLLECTION_NAME);
        $storage = new Storage($collection);

        $repository = new self($storage);

        return $repository;
    }

    /**
     * Zwraca liczbę utworów w podziale na gatunki
     *
     * @param int|null $limit
     * @param array|null $sort
     * @return array
     */
    public function getTrackCountByGenre(?int $limit = null, ?array $sort = null): array
    {
        return $this->aggregateBy('genre', self::FIELD_TYPE_STRING, $limit, $sort);
    }

    /**
     * Zwraca liczbę utworów w podziale na artystów
     *
     * @param int|null $limit
     * @param array|null $sort
     * @return array
     */
    public function getTrackCountByArtist(?int $limit = null, ?array $sort = null): array
    {
        return $this->aggregateBy('artists', self::FIELD_TYPE_ARRAY, $limit, $sort);
    }

    /**
     * Agreguje dokumenty w bazie danych na podstawie przekazanych parametrów
     *
     * @param string $fieldName
     * @param string $fieldType
     * @param int|null $limit
     * @param array|null $sort
     * @return array
     */
    private function aggregateBy(string $fieldName, string $fieldType, ?int $limit, ?array $sort = null): array
    {
        if (empty($sort)) {
            $sort = [ 'count' => Storage::SORT_DESC ];
        }

        $group = [
            '_id' => [ $fieldName => '$' . $fieldName ],
            'count' => [ '$sum' => 1 ],
        ];

        $pipeline = [
            [ '$group' => $group, ],
            [ '$sort' => $sort, ],
            [ '$limit' => $limit, ],
        ];

        if ($fieldType === self::FIELD_TYPE_ARRAY) {
            array_unshift($pipeline, [ '$unwind' => '$' . $fieldName ]);
        }

        // zamienić na array_map
        $rawData = $this->storage->aggregate($pipeline);

        $result = [];

        foreach ($rawData as $group) {
            $result[$group['_id'][$fieldName]] = $group['count'];
        }

        return $result;
    }
}
