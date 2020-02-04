<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\File\Extension\Parser as MetadataParser;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Track\Model\Track;
use MongoDB\BSON\UTCDateTime;

/**
 * Klasa, której zadaniem jest przetwarzanie plików (utworów muzycznych) znajdujących się w kolekcji
 */
class FileReader extends AbstractReader
{
    private Id3Adapter $id3Adapter;

    private MetadataParser $metadataParser;

    /**
     * FileReader constructor.
     *
     * @param Id3Adapter $id3Adapter
     * @param MetadataParser $metadataParser
     */
    public function __construct(Id3Adapter $id3Adapter, MetadataParser $metadataParser)
    {
        parent::__construct();

        $this->id3Adapter = $id3Adapter;
        $this->metadataParser = $metadataParser;
    }

    /**
     * {@inheritDoc}
     *
     * @return Track
     */
    public function read(SplFileInfo $node)
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
        $track->modified_date = new UTCDateTime($node->getMTime());
        $track->indexed_date = new UTCDateTime();

        return $track;
    }

    /**
     * Zwraca guid dla podanego pliku (utworu muzycznego)
     *
     * @param SplFileInfo $node
     * @param array $metadata
     * @return string
     */
    private function getGuid(SplFileInfo $node, array $metadata): string
    {
        $string = isset($metadata['artist'], $metadata['title'])
            ? sprintf('%s - %s', $metadata['artist'], $metadata['title'])
            : $node->getBasename(sprintf('.%s', $node->getExtension()));

        return $this->slugify->slugify($string);
    }
}
