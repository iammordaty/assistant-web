<?php

namespace Assistant\Module\File\Extension;

/**
 * Iterator filtrujący elementy według podanych ścieżek
 */
class PathFilterIterator extends \RecursiveFilterIterator
{
    use Traits\SearchPathTrait;

    /**
     * Katalog główny kolekcji
     *
     * @var string
     */
    private $rootPath;

    /**
     * Lista ścieżek, które mają zostać odrzucone
     *
     * @var string[]
     */
    private $excludedPaths;

    /**
     * Konstruktor
     *
     * @param \Iterator $iterator
     * @param string $rootPath
     * @param array $excludedPaths
     */
    public function __construct(\Iterator $iterator, $rootPath, array $excludedPaths)
    {
        parent::__construct($iterator);

        $this->rootPath = $rootPath;
        $this->excludedPaths = $excludedPaths;
    }

    /**
     * {@inheritDoc}
     */
    public function accept()
    {
        /* @var $current Node */
        $current = parent::current();

        if ($this->searchPath($current->getRelativePathname(), $this->excludedPaths) === true) {
            return false;
        }

        if ($current->getBasename() === '..' || $current->getBasename() === '.' && $current->getRealPath() !== $this->rootPath) {
            return false;
        }

        return $current->isDir() || ($current->isFile() && $current->getExtension() === 'mp3');
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren()
    {
        return new self($this->getInnerIterator()->getChildren(), $this->rootPath, $this->excludedPaths);
    }
}
