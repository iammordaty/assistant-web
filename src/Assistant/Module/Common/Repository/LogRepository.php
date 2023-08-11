<?php

namespace Assistant\Module\Common\Repository;

use Assistant\Module\Common\Model\LogEntry;
use Assistant\Module\Common\Model\LogEntryDto;
use Assistant\Module\Common\Storage\Storage;
use MongoDB\Database;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use Traversable;

/** Repozytorium Logów */
final class LogRepository
{
    public const COLLECTION_NAME = 'logs';

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

    /**
     * @param array|null $query
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return LogEntry[]|Traversable
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

        // https://github.com/mongodb/mongo-php-library/blob/master/src/functions.php#L374
        // https://jira.mongodb.org/browse/PHPC-314

        $documents->setTypeMap([
            'root' => BSONDocument::class,
            'document' => BSONDocument::class,
            'array' => BSONArray::class,
            'fieldPaths' => [
                'context.$' => 'array',
                'extra.$' => 'array',
            ],
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

    private static function createModel(BSONDocument $document): LogEntry
    {
        $dto = LogEntryDto::fromStorage($document->bsonSerialize());
        $logEntry = LogEntry::fromDto($dto);

        return $logEntry;
    }
}
