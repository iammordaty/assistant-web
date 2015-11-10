<?php

namespace Assistant\Module\Common\Extension\GetId3\Adapter;

/**
 * Klasa bazowa adapterów metadanych
 */
abstract class Metadata
{
    /**
     * Surowe dane pochodzące z biblioteki getID3
     *
     * @var array
     */
    protected $rawInfo;

    /**
     * Konstruktor
     *
     * @param array $rawInfo
     */
    public function __construct(array $rawInfo)
    {
        $this->rawInfo = $rawInfo;
    }

    /**
     * Zwraca metadane zawarte pliku (utworze muzycznym)
     *
     * @return array
     */
    abstract public function getMetadata();

    /**
     * Przygotowuje metadane do formatu używanego przez bibliotekę getID3
     *
     * @return array
     */
    abstract public function prepareMetadata(array $metadata);
}
