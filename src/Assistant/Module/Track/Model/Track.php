<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Common\Model\AbstractModel;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;

class Track extends AbstractModel
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

    /**
     * @var string
     */
    protected $parent;

    /**
     * @var string
     */
    protected $pathname;

    /**
     * @var bool
     */
    protected $ignored;

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
}
