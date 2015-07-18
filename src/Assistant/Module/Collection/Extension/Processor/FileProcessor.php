<?php

namespace Assistant\Module\Collection\Extension\Processor;

use Assistant\Module\Common;
use Assistant\Module\Collection;
use Assistant\Module\File;
use Assistant\Module\Track;

/**
 * Klasa, której zadaniem jest przetwarzanie plików (utworów muzycznych) znajdujących się w kolekcji
 */
class FileProcessor extends Collection\Extension\Processor implements ProcessorInterface
{
    /**
     * @var Lib\GetId3\Adapter
     */
    private $id3;

    /**
     * @var Lib\Metadata\Parser
     */
    private $parser;

    /**
     * Konstruktor
     *
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        parent::__construct($parameters);

        $this->id3 = new Common\Extension\GetId3\Adapter();
        $this->parser = new File\Extension\Parser($parameters['track']['metadata']['parser']);
    }

    /**
     * Przetwarza plik (utwór muzyczny) znajdujący się w kolekcji
     *
     * @param File\Extension\Node\File $file
     * @return Track\Model\Track
     * @throws Exception\EmptyMetadataException
     */
    public function process($file)
    {
        $this->id3->analyze($file);
        $metadata = $this->id3->getId3v2Metadata();

        if (isset($metadata['artist']) === false || isset($metadata['title']) === false) {
            throw new Exception\EmptyMetadataException(
                sprintf('Track %s does\'t contains metadata.', $file->getBasename())
            );
        }

        $data = array_merge($metadata, $this->parser->parse($metadata));

        $track = new Track\Model\Track($data);
        $track->guid = $this->slugify->slugify(sprintf('%s - %s', $metadata['artist'], $metadata['title']));
        $track->length = $this->id3->getTrackLength();
        $track->metadata_md5 = md5(json_encode($data));
        $track->parent = $this->slugifyPath(dirname($file->getRelativePathname()));
        $track->pathname = $file->getRelativePathname();
        $track->ignored = $file->isIgnored();
        $track->indexed_date = new \MongoDate();

        return $track;
    }
}
