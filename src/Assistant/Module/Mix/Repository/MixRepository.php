<?php

namespace Assistant\Module\Mix\Repository;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Mix\Model\Mix;
use Assistant\Module\Mix\Model\MixDto;
use Generator;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;

/** Repozytorium Mixów */
final class MixRepository
{
    public const string COLLECTION_NAME = 'mixes';

    public function __construct(private Storage $storage)
    {
    }

    public static function factory(Database $database): self
    {
        $collection = $database->selectCollection(self::COLLECTION_NAME);
        $storage = new Storage($collection);

        $repository = new self($storage);

        return $repository;
    }

    public function findOneBy(array $conditions): ?Mix
    {
        $document = $this->storage->findOneBy($conditions);

        if (!$document) {
            return null;
        }

        $mix = self::createModel($document);

        return $mix;
    }

    /** @return Generator<int, Mix> */
    public function findBy(
        ?array $query = [],
        ?array $sort = null,
        ?int $limit = null,
        ?int $skip = null
    ): Generator {
        $documents = $this->storage->findBy($query, options: [
            'sort' => $sort,
            'limit' => $limit,
            'skip' => $skip,
        ]);

        foreach ($documents as $document) {
            $mix = self::createModel($document);

            yield $mix;
        }
    }

    /**
     * Zwraca informację o liczbie dokumentów w kolekcji
     *
     * @return int
     */
    public function count(): int
    {
        $count = $this->storage->count();

        return $count;
    }

    // Tu do przemyślenia jeszcze: jeśli mix będzie miał mongoId nie trzeba będzie przekazywać dwóch parametrów
    public function save(Mix $mix, Mix $updatedMix): bool
    {
        $dto = $updatedMix->toDto();

        $guid = $mix->guid ?? $dto->guid;
        $hasMix = (bool) $this->storage->count([ 'guid' => $guid ]);

        $result = $hasMix
            ? $this->storage->update([ 'guid' => $guid ], $dto->toStorage())
            : $this->storage->insert($dto->toStorage());

        return (bool) $result;
    }

    public function delete(Mix $mix): bool
    {
        $result = (bool) $this->storage->removeBy([ 'guid' => $mix->guid ]);

        return $result;
    }

    private static function createModel(BSONDocument $document): Mix
    {
        $dto = MixDto::fromStorage($document->bsonSerialize());
        $mix = Mix::fromDto($dto);

        return $mix;
    }
}
