<?php

namespace Assistant\Module\Track\Model;

use SplFileInfo;

final class IncomingTrack
{
    private string $name;
    private string|int|null $trackNumber;
    private bool $trackNumberHasLeadingZero;
    private SplFileInfo $file;

    public function __construct(
        private string $guid,
        private ?string $artist,
        private ?array $artists,
        private ?string $title,
        private ?string $album,
        $trackNumber,
        private ?int $year,
        private ?string $genre,
        private ?string $publisher,
        private ?float $bpm,
        private ?string $initialKey,
        private int $length,
        private array $tags,
        private string $pathname,
    ) {
        $this->file = new SplFileInfo($this->pathname);

        $this->name = $artist && $title
            ? sprintf('%s - %s', $artist, $title)
            : $this->file->getBasename(sprintf('.%s', $this->file->getExtension()));

        $this->trackNumber = $trackNumber !== null ? (int) $trackNumber : null;
        $this->trackNumberHasLeadingZero
            = is_string($trackNumber)
            && (int) $trackNumber < 10
            && $trackNumber[0] === '0';
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function getArtists(): ?array
    {
        return $this->artists;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Zwraca nazwę utworu w pełnej postaci, tj. Wykonawca - Tytuł utworu
     * lub nazwę pliku bez rozszerzenia jako fallback
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

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function getBpm(): ?float
    {
        return $this->bpm;
    }

    public function getInitialKey(): ?string
    {
        return $this->initialKey;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    /** Shorthand method oraz dla zachowania kompatybilności z klasą Track */
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

    public function getFile(): SplFileInfo
    {
        return $this->file;
    }
}
