<?php

namespace Assistant\Module\Directory\Model;

use Assistant\Model as BaseModel;

class Directory extends BaseModel
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
    public $name;

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
