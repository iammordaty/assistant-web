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
    private Id3Adapter $id3Adapter;

    private MetadataParser $metadataParser;

    private SlugifyService $slugify;

    public function __construct(Id3Adapter $id3Adapter, MetadataParser $metadataParser, SlugifyService $slugify)
    {
        $this->id3Adapter = $id3Adapter;
        $this->metadataParser = $metadataParser;
        $this->slugify = $slugify;
    }

    public function read(SplFileInfo $node): Track
    {
        $metadata = $this->id3Adapter
            ->setFile($node)
            ->readId3v2Metadata();

        $parsedMetadata = $this->metadataParser->parse($metadata);

        $modifiedTimestamp = (new \DateTime())->setTimestamp($node->getMTime());
        $indexedTimestamp = new \DateTime();

        // jeśli poszczególne pola w tablicy metadata okażą się puste (np. w widoku przeglądania oczekujących)
        // tymczasowo ustawiać fallback w postaci null-i, pustych stringów, itp
        // i zastanowić się jak to rozwiązać docelowo. poprzez nowy model (np. IncomingTrack?), który zezwala na
        // puste wartości? może jakoś inaczej?

        $track = new Track(
            null,
            $this->getGuid($node, $metadata),
            $metadata['artist'],
            $parsedMetadata['artists'],
            $metadata['title'],
            $metadata['album'] ?? null,
            $metadata['track_number'] ?? null,
            $metadata['year'],
            $metadata['genre'],
            $metadata['publisher'] ?? null,
            $metadata['bpm'],
            $metadata['initial_key'],
            $this->id3Adapter->getTrackLength(),
            [],
            md5(json_encode($metadata)),
            $node->getPath(),
            $node->getPathname(),
            $modifiedTimestamp,
            $indexedTimestamp,
        );

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
