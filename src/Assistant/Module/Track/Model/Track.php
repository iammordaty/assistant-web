<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Common\Model\AbstractModel;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use SplFileInfo;

final class Track extends AbstractModel
{
    /**
     * @var ObjectId
     */
    protected $_id;

    /**
     * @var string
     */
    protected $guid;

    /**
     * @var string
     */
    protected $artist;

    /**
     * @var BSONArray|array
     */
    protected $artists = [];

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $album;

    /**
     * @var int
     */
    protected $track_number;

    /**
     * @var int
     */
    protected $year;

    /**
     * @var string
     */
    protected $genre;

    /**
     * @var string
     */
    protected $publisher;

    /**
     * @var float
     */
    protected $bpm;

    /**
     * @var string
     */
    protected $initial_key;

    /**
     * @var int
     */
    protected $length;

    /**
     * @var string
     */
    protected $metadata_md5;

    /**x`
     * @var string
     */
    protected $parent;

    /**
     * @var string
     */
    protected $pathname;

    /**
    * @var UTCDateTime
    */
    protected $modified_date;

    /**
     * @var UTCDateTime|null
     */
    protected $indexed_date;

    /**
     * @var BSONArray|array
     */
    protected $tags = [];

    private ?SplFileInfo $file = null;

    public function getId(): ObjectId
    {
        return $this->_id;
    }

    public function setId(ObjectId $id): void
    {
        $this->_id = $id;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function setGuid(string $guid): void
    {
        $this->guid = $guid;
    }

    public function getArtist(): string
    {
        return $this->artist;
    }

    public function setArtist(string $artist): void
    {
        $this->artist = $artist;
    }

    /**
     * @return array|BSONArray
     */
    public function getArtists()
    {
        return $this->artists;
    }

    /**
     * @param array|BSONArray $artists
     */
    public function setArtists($artists): void
    {
        $this->artists = $artists;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getAlbum(): string
    {
        return $this->album;
    }

    public function setAlbum(string $album): void
    {
        $this->album = $album;
    }

    public function getTrackNumber(): int
    {
        return $this->track_number;
    }

    public function setTrackNumber(int $track_number): void
    {
        $this->track_number = $track_number;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    public function getGenre(): string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): void
    {
        $this->genre = $genre;
    }

    public function getPublisher(): string
    {
        return $this->publisher;
    }

    public function setPublisher(string $publisher): void
    {
        $this->publisher = $publisher;
    }

    public function getBpm(): ?float
    {
        return $this->bpm;
    }

    public function setBpm(float $bpm): void
    {
        $this->bpm = $bpm;
    }

    public function getInitialKey(): ?string
    {
        return $this->initial_key;
    }

    public function setInitialKey(string $initialKey): void
    {
        $this->initial_key = $initialKey;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function setLength(int $length): void
    {
        $this->length = $length;
    }

    public function getMetadataMd5(): string
    {
        return $this->metadata_md5;
    }

    public function setMetadataMd5(string $metadataMd5): void
    {
        $this->metadata_md5 = $metadataMd5;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function setParent(string $parent): void
    {
        $this->parent = $parent;
    }

    public function getPathname(): string
    {
        return $this->pathname;
    }

    public function setPathname(string $pathname): void
    {
        $this->pathname = $pathname;
    }

    /**
     * @fixme To powinno zwracać \DateTime()
     *
     * @return UTCDateTime
     */
    public function getModifiedDate(): UTCDateTime
    {
        return $this->modified_date;
    }

    /**
     * @fixme To powinno przyjmować \DateTime()
     *
     * @param UTCDateTime $modifiedDate
     */
    public function setModifiedDate(UTCDateTime $modifiedDate): void
    {
        $this->modified_date = $modifiedDate;
    }

    /**
     * @fixme To powinno zwracać \DateTime()
     *
     * @return UTCDateTime|null
     */
    public function getIndexedDate(): ?UTCDateTime
    {
        return $this->indexed_date;
    }

    /**
     * @fixme To powinno przyjmować \DateTime()
     *
     * @param UTCDateTime|null $indexedDate
     */
    public function setIndexedDate(?UTCDateTime $indexedDate): void
    {
        $this->indexed_date = $indexedDate;
    }

    /**
     * @fixme To powinno zwracać array
     *
     * @return array|BSONArray
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @fixme To powinno przyjmować array
     *
     * @param array|BSONArray $tags
     */
    public function setTags($tags): void
    {
        $this->tags = $tags;
    }

    public function getFile(): SplFileInfo
    {
        if (!$this->file) {
            $this->file = new SplFileInfo($this->pathname);
        }

        return $this->file;
    }
}
