<?php

namespace Assistant\Module\Directory\Model;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use stdClass;

final class DirectoryDto
{
    private ?ObjectId $objectId;
    private string $guid;
    private string $name;
    private ?string $parent; /** To dotyczy tylko roota (/collection), które nie ma katalogu nadrzędnego. */
    private string $pathname;
    private UTCDateTime $modifiedDate;
    private ?UTCDateTime $indexedDate;

    public function __construct(
        ?ObjectId $objectId,
        string $guid,
        string $name,
        ?string $parent,
        string $pathname,
        UTCDateTime $modifiedDate,
        ?UTCDateTime $indexedDate,
    ) {
        $this->objectId = $objectId;
        $this->guid = $guid;
        $this->name = $name;
        $this->parent = $parent;
        $this->pathname = $pathname;
        $this->modifiedDate = $modifiedDate;
        $this->indexedDate = $indexedDate;
    }

    public static function fromStorage(stdClass $document): self
    {
        $dto = new self(
            $document->_id,
            $document->guid,
            $document->name,
            $document->parent ?? null,
            $document->pathname,
            $document->modified_date,
            $document->indexed_date,
        );

        return $dto;
    }

    public static function fromModel(Directory $directory): self
    {
        $modifiedTimestamp = (int) $directory->getModifiedDate()->format('U') * 1000;
        $indexedTimestamp = $directory->getIndexedDate()
            ? (int) $directory->getIndexedDate()->format('U') * 1000
            : null;

        $dto = new self(
            $directory->getId() ? new ObjectId($directory->getId()) : null,
            $directory->getGuid(),
            $directory->getName(),
            $directory->getParent(),
            $directory->getPathname(),
            new UTCDateTime($modifiedTimestamp),
            $indexedTimestamp ? new UTCDateTime($indexedTimestamp) : null,
        );

        return $dto;
    }

    public function toStorage(): array
    {
        return [
            '_id' => $this->objectId,
            'guid' => $this->guid,
            'name' => $this->name,
            'parent' => $this->parent,
            'pathname' => $this->pathname,
            'modified_date' => $this->modifiedDate,
            'indexed_date' => $this->indexedDate,
        ];
    }

    public function getObjectId(): ?ObjectId
    {
        return $this->objectId;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function getPathname(): string
    {
        return $this->pathname;
    }

    public function getModifiedDate(): UTCDateTime
    {
        return $this->modifiedDate;
    }

    public function getIndexedDate(): ?UTCDateTime
    {
        return $this->indexedDate;
    }
}
