<?php

namespace Assistant\Module\Directory\Model;

use Assistant\Module\Common\Model\AbstractModel;

class Directory extends AbstractModel
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
    protected $name;

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
