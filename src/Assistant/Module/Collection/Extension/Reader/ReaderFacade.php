<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\File\Extension\Parser as MetadataParser;
use Assistant\Module\Track\Model\Track;
use Slim\Helper\Set as Container;
use SplFileInfo;

/**
 * Fasada dla procesorów przetwarzających elementy znajdujące się w kolekcji
*/
final class ReaderFacade
{
    private DirectoryReader $directoryReader;

    private FileReader $fileReader;

    public function __construct(DirectoryReader $directoryReader, FileReader $fileReader)
    {
        $this->directoryReader = $directoryReader;
        $this->fileReader = $fileReader;
    }

    public static function factory(Container $container): ReaderFacade
    {
        $directoryReader = new DirectoryReader();

        $fileReader = new FileReader(
            new Id3Adapter(),
            new MetadataParser($container['parameters']['track']['metadata']['parser']),
        );

        return new self($directoryReader, $fileReader);
    }

    /**
     * @param SplFileInfo $node
     * @return Directory|Track
     */
    public function read(SplFileInfo $node)
    {
        if ($node->isFile()) {
            return $this->fileReader->read($node);
        }

        if ($node->isDir()) {
            return $this->directoryReader->read($node);
        }
    }
}
