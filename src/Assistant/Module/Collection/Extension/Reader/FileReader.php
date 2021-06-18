<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\File\Extension\Parser as MetadataParser;
use Assistant\Module\Track\Model\Track;
use SplFileInfo;

/**
 * Klasa, której zadaniem jest odczytywanie plików (utworów muzycznych) znajdujących się w kolekcji
 */
final class FileReader implements ReaderInterface
{
    public function __construct(
        private Id3Adapter $id3Adapter,
        private MetadataParser $metadataParser,
        private SlugifyService $slugify
    ) {
    }

    public function read(SplFileInfo $node): Track
    {
        $metadata = $this->id3Adapter
            ->setFile($node)
            ->readId3v2Metadata();

        $parsedMetadata = $this->metadataParser->parse($metadata);
        $modifiedDate = (new \DateTime())->setTimestamp($node->getMTime());

        $track = new Track(
            id: null,
            guid: $this->getGuid($metadata),
            artist: $metadata['artist'],
            artists: $parsedMetadata['artists'],
            title: $metadata['title'],
            album: $metadata['album'] ?? null,
            trackNumber: $metadata['track_number'] ?? null,
            year: $metadata['year'] ?? null, // starszy kawałek w kolekcji
            genre: $metadata['genre'],
            publisher: $metadata['publisher'] ?? null,
            bpm: $metadata['bpm'],
            initialKey: $metadata['initial_key'],
            length: $this->id3Adapter->getTrackLength(),
            tags: [],
            metadataMd5: md5(json_encode($metadata)),
            parent: $this->slugify->slugifyPath($node->getPath()),
            pathname: $node->getPathname(),
            modifiedDate: $modifiedDate,
        );

        return $track;
    }

    /** Zwraca guid dla podanego pliku (utworu muzycznego) */
    private function getGuid(array $metadata): string
    {
        $string = sprintf('%s - %s', $metadata['artist'], $metadata['title']);

        return $this->slugify->slugify($string);
    }
}
