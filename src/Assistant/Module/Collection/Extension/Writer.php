<?php

namespace Assistant\Module\Collection\Extension;

/**
 * Klasa bazowa dla writerów
 */
abstract class Writer
{
    /**
     * Obiekt repozytorium
     *
     * @var \Assistant\Repository
     */
    protected $repository;

    /**
     * @var \MongoDB
     */
    protected $db;

    /**
     * Konstruktor
     *
     * @param \MongoDB $db
     */
    public function __construct(\MongoDB $db)
    {
        $this->db = $db;
    }

    /**
     * Zwraca typ writera
     *
     * @return string
     */
    public function getType()
    {
        $parts = explode('\\', static::class);

        return strtolower(str_replace('Writer', '', array_pop($parts)));
    }
}
