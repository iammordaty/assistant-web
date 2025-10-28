<?php

namespace Assistant\Module\Mix\Repository;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Mix\Model\Mix;
use Assistant\Module\Mix\Model\MixDto;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;
use Traversable;

/** Repozytorium Mixów */
final class MixRepository
{
    public const COLLECTION_NAME = 'mixes';

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

        $directory = self::createModel($document);

        return $directory;
    }

    /**
     * @param array|null $query
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return Mix[]|Traversable
     */
    public function findBy(
        ?array $query = [],
        ?array $sort = null,
        ?int $limit = null,
        ?int $skip = null
    ): array|Traversable {
        $documents = $this->storage->findBy($query, options: [
            'sort' => $sort,
            'limit' => $limit,
            'skip' => $skip,
        ]);

        foreach ($documents as $document) {
            $logEntry = self::createModel($document);

            yield $logEntry;
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

    public function save(Mix $mix): bool
    {
        $dto = $mix->toDto();
        
        // Sprawdź czy miks już istnieje w bazie
        $existingDocument = $this->storage->findOneBy(['guid' => $dto->guid]);
        
        if ($existingDocument) {
            // Update istniejącego miksu
            $modifiedCount = $this->storage->update(['guid' => $dto->guid], $dto->toStorage());
            return $modifiedCount > 0;
        } else {
            // Insert nowego miksu
            $result = $this->storage->insert($dto->toStorage());
            return $result;
        }
    }

    private static function createModel(BSONDocument $document): Mix
    {
        $dto = MixDto::fromStorage($document->bsonSerialize());
        $logEntry = Mix::fromDto($dto);

        return $logEntry;
    }
}
