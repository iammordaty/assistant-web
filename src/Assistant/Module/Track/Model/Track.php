<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Common\Model\CollectionItemInterface;
use DateTime;
use SplFileInfo;

final class Track implements CollectionItemInterface
{
    private ?string $id;
    private string $guid;
    private string $artist;
    private array $artists;
    private string $title;
    private ?string $album;
    private ?int $trackNumber;
    private ?int $year;
    private string $genre;
    private ?string $publisher;

    /**
     * @fixme nulle dla bpm i klucza dozwolone tylko tymczasowo, ze względu na to że niektóre kawałki ich nie mają
     *        (np. Laidback Luke - 05 - Break Down The House [Acapella]). To powinno być ograne w jakoś inaczej,
     *        ale bez zezwolenia na pustą wartość, np. poprzez wcześniejszą walidację w IndexerTask.
     *        Zerknąć też na komentarz na FileReader::read(), to musi być oddzielone od przeglądania nowych (jak?)
     * @see \Assistant\Module\Collection\Extension\Reader\FileReader::read()
     *
     * @var float|null
     */
    private ?float $bpm;

    /**
     * @fixme jak wyżej
     *
     * @var string|null
     */
    private ?string $initialKey;
    private int $length;
    private array $tags;
    private string $metadataMd5;
    private string $parent;
    private string $pathname;
    private DateTime $modifiedDate;
    private DateTime $indexedDate;
    private ?SplFileInfo $file = null;

    /** @noinspection DuplicatedCode */
    public function __construct(
        ?string $id,
        string $guid,
        string $artist,
        array $artists,
        string $title,
        ?string $album,
        ?int $trackNumber,
        ?int $year,
        string $genre,
        ?string $publisher,
        ?float $bpm,
        ?string $initialKey,
        int $length,
        array $tags,
        string $metadataMd5,  // być może to powinno być wyliczane w modelu
        string $parent,
        string $pathname,
        DateTime $modifiedDate,
        DateTime $indexedDate
    ) {
        $this->id = $id;
        $this->guid = $guid;
        $this->artist = $artist;
        $this->artists = $artists;
        $this->title = $title;
        $this->album = $album;
        $this->trackNumber = $trackNumber;
        $this->year = $year;
        $this->genre = $genre;
        $this->publisher = $publisher;
        $this->bpm = $bpm;
        $this->initialKey = $initialKey;
        $this->length = $length;
        $this->tags = $tags;
        $this->metadataMd5 = $metadataMd5;
        $this->parent = $parent;
        $this->pathname = $pathname;
        $this->modifiedDate = $modifiedDate;
        $this->indexedDate = $indexedDate;
    }

    public static function fromDto(TrackDto $dto): self
    {
        $track = new self(
            (string) $dto->getObjectId(),
            $dto->getGuid(),
            $dto->getArtist(),
            $dto->getArtists()->getArrayCopy(),
            $dto->getTitle(),
            $dto->getAlbum() ,
            $dto->getTrackNumber() ,
            $dto->getYear(),
            $dto->getGenre(),
            $dto->getPublisher() ,
            $dto->getBpm(),
            $dto->getInitialKey(),
            $dto->getLength(),
            $dto->getTags()->getArrayCopy(),
            $dto->getMetadataMd5(),
            $dto->getParent(),
            $dto->getPathname(),
            $dto->getModifiedDate()->toDateTime(),
            $dto->getIndexedDate()->toDateTime(),
        );

        return $track;
    }

    public function toDto(): TrackDto
    {
        $dto = TrackDto::fromModel($this);

        return $dto;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function withId(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function withGuid(string $guid): self
    {
        $clone = clone $this;
        $clone->guid = $guid;

        return $clone;
    }

    public function getArtist(): string
    {
        return $this->artist;
    }

    public function getArtists(): array
    {
        return $this->artists;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAlbum(): ?string
    {
        return $this->album;
    }

    public function getTrackNumber(): ?int
    {
        return $this->trackNumber;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function withYear(int $year): self
    {
        $clone = clone $this;
        $clone->year = $year;

        return $clone;
    }

    public function getGenre(): string
    {
        return $this->genre;
    }

    public function withGenre(string $genre): self
    {
        $clone = clone $this;
        $clone->genre = $genre;

        return $clone;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function getBpm(): float
    {
        return $this->bpm;
    }

    public function withBpm(float $bpm): self
    {
        $clone = clone $this;
        $clone->bpm = $bpm;

        return $clone;
    }

    public function getInitialKey(): string
    {
        return $this->initialKey;
    }

    public function withInitialKey(string $initialKey): self
    {
        $clone = clone $this;
        $clone->initialKey = $initialKey;

        return $clone;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getMetadataMd5(): string
    {
        return $this->metadataMd5;
    }

    public function getParent(): string
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

    public function withModifiedDate(DateTime $modifiedDate): self
    {
        $clone = clone $this;
        $clone->modifiedDate = $modifiedDate;

        return $clone;
    }

    public function getIndexedDate(): DateTime
    {
        return $this->indexedDate;
    }

    public function getFile(): SplFileInfo
    {
        if (!$this->file) {
            $this->file = new SplFileInfo($this->pathname);
        }

        return $this->file;
    }
}
