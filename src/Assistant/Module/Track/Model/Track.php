<?php

namespace Assistant\Module\Track\Model;

use Assistant\Model as BaseModel;

class Track extends BaseModel
{
    /**
     * @var \MongoId
     */
    public $_id;

    /**
     * @var string
     */
    public $guid;

    /**
     * @var string
     */
    public $artist;

    /**
     * @var string[]
     */
    public $artists;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $album;

    /**
     * @var int
     */
    public $track_number;

    /**
     * @var int
     */
    public $year;

    /**
     * @var string
     */
    public $genre;

    /**
     * @var int
     */
    public $bpm;

    /**
     * @var string
     */
    public $initial_key;

    /**
     * @var int
     */
    public $length;

    /**
     * @var string
     */
    public $metadata_md5;

    /**
     * @var string
     */
    public $parent;

    /**
     * @var string
     */
    public $pathname;

    /**
     * @var bool
     */
    public $ignored;

    /**
     * @var \MongoDate
     */
    public $indexed_date;
}
