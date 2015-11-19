<?php

namespace Assistant\Module\Track\Model;

use Assistant\Module\Common\Model\AbstractModel;

class Track extends AbstractModel
{
    /**
     * @var \MongoId
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
     * @var string[]
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
     * @var \MongoDate
     */
    protected $indexed_date;
}
