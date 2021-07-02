<?php

namespace Assistant\Module\Collection\Extension\Reader;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Track\Model\IncomingTrack;
use SplFileInfo;

/**
 * Fasada dla klas, której zadaniem jest odczytywanie plików (utworów muzycznych),
 * które oczekują na dodanie do bazy danych kolekcji
 */
final class IncomingFileReader implements ReaderInterface
{
    public function __construct(private Id3Adapter $id3Adapter, private SlugifyService $slugify)
    {
    }

    public function read(SplFileInfo $node): IncomingTrack
    {
        $metadata = $this->id3Adapter
            ->setFile($node)
            ->readId3v2Metadata();

        $incomingTrack = new IncomingTrack(
            guid: $this->getGuid($node, $metadata),
            artist: $metadata['artist'] ?? null,
            title: $metadata['title'] ?? null,
            album: $metadata['album'] ?? null,
            trackNumber: $metadata['track_number'] ?? null,
            year: $metadata['year'] ?? null,
            genre: $metadata['genre'] ?? null,
            publisher: $metadata['publisher'] ?? null,
            bpm: $metadata['bpm'] ?? null,
            initialKey: $metadata['initial_key'] ?? null,
            pathname: $node->getPathname(),
        );

        return $incomingTrack;
    }

    /** Zwraca guid dla podanego pliku (utworu muzycznego) */
    private function getGuid(SplFileInfo $node, array $metadata): string
    {
        $string = isset($metadata['artist'], $metadata['title'])
            ? sprintf('%s - %s', $metadata['artist'], $metadata['title'])
            : $node->getBasename(sprintf('.%s', $node->getExtension()));

        return $this->slugify->slugify($string);
    }
}
