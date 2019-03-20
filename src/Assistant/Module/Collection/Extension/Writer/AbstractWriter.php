<?php

namespace Assistant\Module\Collection\Extension\Writer;

use MongoDB;

/**
 * Klasa bazowa dla writerów
 */
abstract class AbstractWriter
{
    /**
     * @var MongoDB
     */
    protected $db;

    /**
     * Konstruktor
     *
     * @param MongoDB $db
     */
    public function __construct(MongoDB $db)
    {
        $this->db = $db;
    }

    abstract public function save($element);

    abstract public function clean();
}
