<?php

namespace Assistant\Module\File\Extension;

/**
 * Iterator oznaczający elementy kolekcji jako ignorowane
 */
class IgnoredPathIterator extends \RecursiveIteratorIterator
{
    use Traits\SearchPathTrait;

    /**
     * @var array
     */
    protected $ignoredPaths;

    /**
     * Tryb pracy
     *
     * @var int
     */
    protected $mode;

    /**
     * Flagi iteratora
     *
     * @var int
     */
    protected $flags;

    /**
     * Konstruktor
     *
     * @param \RecursiveIterator $iterator
     * @param array $ignoredPaths
     * @param int $mode
     * @param int $flags
     */
    public function __construct(\RecursiveIterator $iterator, array $ignoredPaths, $mode = \RecursiveIteratorIterator::LEAVES_ONLY, $flags = 0)
    {
        parent::__construct($iterator, $mode, $flags);

        $this->ignoredPaths = $ignoredPaths;
        $this->mode = $mode;
        $this->flags = $flags;
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        /* @var $current Node */
        $current = parent::current();

        $current->setAsIgnored($this->searchPath($current->getRelativePathname(), $this->ignoredPaths));

        return $current;
    }
}
