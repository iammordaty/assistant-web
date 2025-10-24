<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use DateTime;
use SplFileInfo;

final class Track implements CollectionItemInterface
{
    public string $name;
    private string|int|null $trackNumber;
    private bool $trackNumberHasLeadingZero;
    private ?SplFileInfo $file = null;

    /**
     * @fixme: nulle dla bpm i klucza dozwolone tylko tymczasowo, ze względu na to że niektóre kawałki ich nie mają
     *        c(np. Laidback Luke - 05 - Break Down The House [Acapella]). To powinno być ograne w jakoś inaczej,
     *         ale bez zezwolenia na pustą wartość, np. poprzez wcześniejszą walidację w IndexerTask.
     */
    public function __construct(
        private ?string $id,
        public string $guid,
        private string $artist,
        public array $artists,
        public string $title,
        private ?string $album,
        string|int|null $trackNumber,
        private ?int $year,
        public string $genre,
        private ?string $publisher,
        public ?float $bpm,
        public ?string $initialKey,
        public int $length,
        private array $tags,
        private bool $isFavorite,
        private string $metadataMd5,  // @idea: być może to powinno być wyliczane w modelu
        private string $parent,
        private string $pathname,
        private DateTime $modifiedDate,
        private ?DateTime $indexedDate = null,
    ) {
        $this->name = $artist . ' - ' . $title;

        $this->trackNumber = $trackNumber !== null ? (int) $trackNumber : null;
        $this->trackNumberHasLeadingZero
            = is_string($trackNumber)
            && (int) $trackNumber < 10
            && $trackNumber[0] === '0';
    }

    public static function fromDto(TrackDto $dto): self
    {
        $track = new self(
            (string) $dto->objectId,
            $dto->guid,
            $dto->artist,
            $dto->artists->getArrayCopy(),
            $dto->title,
            $dto->album,
            $dto->trackNumber,
            $dto->year,
            $dto->genre,
            $dto->publisher,
            $dto->bpm,
            $dto->initialKey,
            $dto->length,
            $dto->tags->getArrayCopy(),
            $dto->isFavorite,
            $dto->metadataMd5,
            $dto->parent,
            $dto->pathname,
            $dto->modifiedDate->toDateTime(),
            $dto->indexedDate->toDateTime(),
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

    /**
     * Zwraca nazwę utworu w pełnej postaci, tj. Wykonawca - Tytuł utworu
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getAlbum(): ?string
    {
        return $this->album;
    }

    public function getTrackNumber(): ?int
    {
        return $this->trackNumber;
    }

    public function isTrackNumberHasLeadingZero(): bool
    {
        return $this->trackNumberHasLeadingZero;
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

    public function withTags(array $tags): self
    {
        $clone = clone $this;
        $clone->tags = $tags;

        return $clone;
    }

    public function getIsFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function withIsFavorite(bool $isFavorite): self
    {
        $clone = clone $this;
        $clone->isFavorite = $isFavorite;

        return $clone;
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

    public function withPathname(string $pathname): self
    {
        $clone = clone $this;
        $clone->pathname = $pathname;
        $clone->file = new SplFileInfo($pathname);

        return $clone;
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

    public function withFile(SplFileInfo $file): self
    {
        $clone = clone $this;
        $clone->file = $file;
        $clone->pathname = $file->getPathname();

        return $clone;
    }
}
