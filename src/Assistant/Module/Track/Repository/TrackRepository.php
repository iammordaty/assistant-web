<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Repository\Storage;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Model\TrackDto;
use DateTime;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Traversable;

/**
 * Repozytorium obiektów Track
 */
final class TrackRepository
{
    private const COLLECTION_NAME = 'tracks';

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
     * @param string|Regex $guid
     * @return Track|null
     */
    public function getByGuid($guid): ?Track
    {
        $track = $this->findOneBy([ 'guid' => $guid ]);

        return $track;
    }

    /**
     * @param string|Regex $name
     * @return Track|null
     */
    public function getByName($name): ?Track
    {
        $track = $this->findOneBy([
            '$or' => [
                [ 'artist' => $name ],
                [ 'title' => $name ],
            ]
        ]);

        return $track;
    }

    public function getByPathname(Track $track): ?Track
    {
        $track = $this->findOneBy([ 'pathname' => $track->getPathname() ]);

        return $track;
    }

    /**
     * @param Directory $directory
     * @return Traversable|Track[]
     */
    public function getChildren(Directory $directory): Traversable
    {
        $directories = $this->findBy(
            [ 'parent' => $directory->getGuid() ],
            [ 'guid' => Storage::SORT_ASC ]
        );

        return $directories;
    }

    /**
     * @param DateTime|null $from
     * @return Track[]|Traversable
     */
    public function getRecentTracks(?DateTime $from = null)
    {
        if (!$from) {
            $from = new DateTime();

            $from->modify('-3 years first day of january');
        }

        $tracks = $this->findBy(
            [ 'indexed_date' => [ '$gte' => new UTCDateTime($from->getTimestamp() * 1000) ] ],
            [ 'indexed_date' => -1 ]
        );

        return $tracks;
    }

    public function save(Track $track): bool
    {
        $dto = $track->toDto();

        if ($dto->getObjectId()) {
            $result = $this->storage->updateById($dto->getObjectId(), $dto->toStorage());
        } else {
            $result = $this->storage->insert($dto->toStorage());
        }

        return $result;
    }

    public function delete(Track $track): bool
    {
        $dto = $track->toDto();

        return $this->storage->removeById($dto->getObjectId());
    }

    /**
     * @deprecated Publiczna tymczasowo, ta metoda powinna być prywatna
     *
     * @param array $conditions
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return Traversable|Track[]
     */
    public function findBy(array $conditions, ?array $sort = [], ?int $limit = null, ?int $skip = null): Traversable
    {
        $options = [];

        if ($sort) {
            $options['sort'] = $sort;
        }

        if ($limit) {
            $options['limit'] = $limit;
        }

        if ($skip) {
            $options['skip'] = $skip;
        }

        $documents = $this->storage->findBy($conditions, $options);

        foreach ($documents as $document) {
            $track = self::createModel($document);

            yield $track;
        }
    }

    /**
     * Zwraca informację o liczbie dokumentów w kolekcji na podstawie podanych kryteriów
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        return $this->storage->count($conditions);
    }

    private function findOneBy(array $conditions): ?Track
    {
        $document = $this->storage->findOneBy($conditions);

        if (!$document) {
            return null;
        }

        $track = self::createModel($document);

        return $track;
    }

    private static function createModel($document): Track
    {
        $dto = TrackDto::fromStorage($document->bsonSerialize());
        $track = Track::fromDto($dto);

        return $track;
    }
}
