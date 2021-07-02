<?php

namespace Assistant\Module\Directory\Model;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use DateTime;
use SplFileInfo;

final class Directory implements CollectionItemInterface
{
    private ?string $id;
    private string $guid;
    private string $name;
    private ?string $parent;
    private string $pathname;
    private DateTime $modifiedDate;
    private ?DateTime $indexedDate;
    private ?SplFileInfo $file = null;

    public function __construct(
        ?string $id,
        string $guid,
        string $name,
        ?string $parent,
        string $pathname,
        DateTime $modifiedDate,
        ?DateTime $indexedDate = null,
    ) {
        $this->id = $id;
        $this->guid = $guid;
        $this->name = $name;
        $this->parent = $parent;
        $this->pathname = $pathname;
        $this->modifiedDate = $modifiedDate;
        $this->indexedDate = $indexedDate;
    }

    public static function fromDto(DirectoryDto $dto): self
    {
        $directory = new self(
            (string) $dto->getObjectId(),
            $dto->getGuid(),
            $dto->getName(),
            $dto->getParent(),
            $dto->getPathname(),
            $dto->getModifiedDate()->toDateTime(),
            $dto->getIndexedDate()->toDateTime(),
        );

        return $directory;
    }

    public function toDto(): DirectoryDto
    {
        $dto = DirectoryDto::fromModel($this);

        return $dto;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getGuid(): ?string
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

    public function getModifiedDate(): DateTime
    {
        return $this->modifiedDate;
    }

    public function getIndexedDate(): ?DateTime
    {
        return $this->indexedDate;
    }

    public function withIndexedDate(DateTime $indexedDate): self
    {
        $clone = clone $this;
        $clone->indexedDate = $indexedDate;

        return $clone;
    }

    public function getFile(): SplFileInfo
    {
        if (!$this->file) {
            $this->file = new SplFileInfo($this->pathname);
        }

        return $this->file;
    }
}
