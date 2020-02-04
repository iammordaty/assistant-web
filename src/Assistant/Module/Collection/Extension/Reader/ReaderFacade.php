<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\File\Extension\Parser as MetadataParser;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Track\Model\Track;

/**
 * Fasada dla procesorów przetwarzających elementy znajdujące się w kolekcji
*/
class ReaderFacade
{
    private DirectoryReader $directoryReader;

    private FileReader $fileReader;

    public function __construct(array $parameters)
    {
        $this->directoryReader = new DirectoryReader();

        $this->fileReader = new FileReader(
            new Id3Adapter(),
            new MetadataParser($parameters['track']['metadata']['parser']),
        );
    }

    /**
     * @param SplFileInfo $node
     * @return Directory|Track
     */
    public function read(SplFileInfo $node)
    {
        $nodeType = $node->getType();

        if ($nodeType === 'file') {
            return $this->fileReader->read($node);
        }

        if ($node->getType() === 'dir') {
            return $this->directoryReader->read($node);
        }
    }
}
