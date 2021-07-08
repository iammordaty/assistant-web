<?php

namespace Assistant\Module\Track\Model;

use SplFileInfo;

final class IncomingTrack
{
    private string $name;
    private SplFileInfo $file;

    public function __construct(
        private string $guid,
        private ?string $artist,
        private ?string $title,
        private ?string $album,
        private ?int $trackNumber,
        private ?int $year,
        private ?string $genre,
        private ?string $publisher,
        private ?float $bpm,
        private ?string $initialKey,
        private string $pathname,
    ) {
        $this->file = new SplFileInfo($this->pathname);
        $this->name = $artist && $title
            ? $artist . ' - ' . $title
            : $this->file->getBasename(sprintf('.%s', $this->file->getExtension()));
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
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

    /** Shorthand method oraz dla zachowania kompatybilności z klasą Track */
    public function getPathname(): string
    {
        return $this->pathname;
    }

    public function getFile(): SplFileInfo
    {
        return $this->file;
    }
}
