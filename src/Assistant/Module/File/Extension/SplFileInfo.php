<?php

namespace Assistant\Module\File\Extension;

/**
 * Rozszerzenie \SplFileInfo wspierające relatywne ścieżki do plików
 */
class SplFileInfo extends \SplFileInfo
{
    /**
     * Relatywna, w stosunku do katalogu głównego, ścieżka do elementu kolekcji
     *
     * @var string
     */
    private $relativePathname;

    /**
     * Określa, czy element jest ignorowany
     *
     * @var bool
     */
    private $ignored;

    /**
     * Konstruktor
     *
     * @param string $filename
     * @param string $relativePathname
     */
    public function __construct($filename, $relativePathname)
    {
        parent::__construct($filename);

        $this->ignored = false;
        $this->relativePathname = sprintf('/%s', $relativePathname);
    }

    /**
     * Zwraca informację, czy element jest ignorowany
     *
     * @return bool
     */
    public function isIgnored()
    {
        return $this->ignored;
    }

    /**
     * Ustawia flagę ignorowania dla elementu
     *
     * @param bool $ignored
     * @return self
     */
    public function setAsIgnored($ignored)
    {
        $this->ignored = (bool) $ignored;

        return $this;
    }

    /**
     * Zwraca relatywną, w stosunku do katalogu głównego, ścieżkę do elementu kolekcji
     *
     * @return string
     */
    public function getRelativePathname()
    {
        return $this->relativePathname;
    }
}
