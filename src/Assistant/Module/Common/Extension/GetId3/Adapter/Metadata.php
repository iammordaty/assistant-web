<?php

namespace Assistant\Module\Common\Extension\GetId3\Adapter;

abstract class Metadata
{
    /**
     * Surowe dane pochodzące z biblioteki getID3
     *
     * @var array
     */
    protected $rawInfo;

    public function __construct($rawInfo)
    {
        $this->rawInfo = $rawInfo;
    }

    abstract public function getMetadata();
}
