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
     * @var \Assistant\Module\Common\Repository\AbstractObjectRepository
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
     * Usuwa elementy znajdujące się w kolekcji
     *
     * @return int
     */
    public function clean()
    {
        return $this->repository->removeBy();
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
