<?php

namespace Assistant\Module\Directory\Model;

use Assistant\Module\Common\Model\AbstractModel;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class Directory extends AbstractModel
{
    /**
     * @var ObjectId
     */
    protected ObjectId $_id;

    /**
     * @var string|null
     */
    protected ?string $guid;

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var string|null
     */
    protected ?string $parent;

    /**
     * @var string
     */
    protected string $pathname;

    /**
     * @var bool
     */
    protected bool $ignored;

    /**
     * @var UTCDateTime
     */
    protected UTCDateTime $modified_date;

    /**
     * @var UTCDateTime
     */
    protected UTCDateTime $indexed_date;

    /**
     * {@inheritDoc}
     */
    public function __construct($data = null)
    {
        $data['guid'] ??= null;
        $data['parent'] ??= null;

        parent::__construct($data);
    }
}
