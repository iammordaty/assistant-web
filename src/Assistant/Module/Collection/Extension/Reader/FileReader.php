<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\File\Extension\Parser as MetadataParser;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Track\Model\Track;
use MongoDate;

/**
 * Klasa, której zadaniem jest przetwarzanie plików (utworów muzycznych) znajdujących się w kolekcji
 */
class FileReader extends AbstractReader
{
    /**
     * @var Id3Adapter
     */
    private $id3Adapter;

    /**
     * @var MetadataParser
     */
    private $metadataParser;

    /**
     * Konstruktor
     *
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        parent::__construct($parameters);

        $this->id3Adapter = new Id3Adapter();
        $this->metadataParser = new MetadataParser($parameters['track']['metadata']['parser']);
    }

    /**
     * {@inheritDoc}
     *
     * @return Track
     */
    public function process(SplFileInfo $node)
    {
        $metadata = $this->id3Adapter
            ->setFile($node)
            ->readId3v2Metadata();

        $data = array_merge($metadata, $this->metadataParser->parse($metadata));

        $track = new Track($data);
        $track->guid = $this->getGuid($node, $metadata);
        $track->length = $this->id3Adapter->getTrackLength();
        $track->metadata_md5 = md5(json_encode($data));
        $track->parent = $this->slugifyPath(dirname($node->getRelativePathname()));
        $track->pathname = $node->getRelativePathname();
        $track->ignored = $node->isIgnored();
        $track->modified_date = new MongoDate($node->getMTime());
        $track->indexed_date = new MongoDate();

        return $track;
    }

    /**
     * Zwraca guid dla podanego pliku (utworu muzycznego)
     *
     * @param SplFileInfo $node
     * @return Track
     */
    private function getGuid(SplFileInfo $node, array $metadata)
    {
        $string = isset($metadata['artist']) && isset($metadata['title'])
            ? sprintf('%s - %s', $metadata['artist'], $metadata['title'])
            : $node->getBasename(sprintf('.%s', $node->getExtension()));

        return $this->slugify->slugify($string);
    }
}
