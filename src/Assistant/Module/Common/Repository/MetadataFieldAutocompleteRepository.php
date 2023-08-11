<?php

namespace Assistant\Module\Common\Repository;

use Assistant\Module\Collection\Extension\Autocomplete\MetadataFieldAutocompleteEntry;
use Assistant\Module\Common\Storage\Storage;
use MongoDB\BSON\Regex;
use MongoDB\Database;

final readonly class MetadataFieldAutocompleteRepository
{
    public const COLLECTION_NAME = 'tracks';

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

    /** @return MetadataFieldAutocompleteEntry[] */
    public function get(string $query, string $type, int $limit): array
    {
        $documents = $this->storage->distinct($type, [ $type => new Regex('^' . $query, 'i') ]);

        if (count($documents) < $limit) {
            $additional = $this->storage->distinct($type, [ $type => new Regex($query, 'i') ]);

            foreach ($additional as $document) {
                if (!in_array($document, $documents)) {
                    $documents[] = $document;
                }
            }
        }

        $entries = array_map(
            fn (string $name) => new MetadataFieldAutocompleteEntry($type, $name),
            $documents
        );

        return $entries;
    }
}
