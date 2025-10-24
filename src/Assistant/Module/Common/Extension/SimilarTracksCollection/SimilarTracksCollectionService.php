<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

use Assistant\Module\Common\Extension\Config;
use Musly\Collection;
use Musly\Exception\RuntimeException;
use Musly\Musly;
use SplFileInfo;

final class SimilarTracksCollectionService
{
    private const COLLECTION_PATHNAME = 'collection.musly';
    private const SIMILAR_TRACKS_LIMIT = 200; // @idea Zastanowić się nad zwiększeniem lub uelastycznieniem limitu
    private const WITH_TRACK_DISTANCE = '-o long';

    private Musly $musly;

    public function __construct(private Config $config)
    {
        $pathname = $this->config->get('collection.metadata_dirs.music_similarity') . '/' . self::COLLECTION_PATHNAME;

        $musly = new Musly();

        $collection = new Collection([
            'pathname' => $pathname,
            'jukeboxPathname' => Collection::USE_DEFAULT_JUKEBOX_PATHNAME,
        ]);

        if (!$collection->isInitialized()) {
            $musly->initializeCollection($collection);
        }

        $musly->setCollection($collection);

        $this->musly = $musly;
    }

    public function add(SplFileInfo $collectionItem): bool
    {
        try {
            $this->musly->analyze($collectionItem->getPathname());
        } catch (RuntimeException $e) {
            $error = sprintf('An error occurred while adding track to collection: %s.', $e->getMessage());

            throw new SimilarTracksCollectionException($error);
        }

        return true;
    }

    public function getSimilarTracks(SplFileInfo $track): SimilarTracksResultList
    {
        try {
            $similarTracks = $this->musly->getSimilarTracks(
                pathname: $track->getPathname(),
                num: self::SIMILAR_TRACKS_LIMIT,
                extraParams: self::WITH_TRACK_DISTANCE,
            );
        } catch (RuntimeException $e) {
            $error = sprintf('An error occurred while retrieving similar tracks: %s.', $e->getMessage());

            throw new SimilarTracksCollectionException($error);
        }

        $similarTracksResults = SimilarTracksResultList::factory($track, $similarTracks);

        return $similarTracksResults;
    }

    public function getTracks(): array
    {
        try {
            $tracks = $this->musly->getAllTracks();
        } catch (RuntimeException $e) {
            $error = sprintf('An error occurred while retrieving tracks from collection: %s.', $e->getMessage());

            throw new SimilarTracksCollectionException($error);
        }

        return $tracks;
    }

    public function initializeCollection(): bool
    {
        return $this->musly->initializeCollection($this->musly->getCollection());
    }

    public function getCollectionPathname(): string
    {
        return self::COLLECTION_PATHNAME;
    }

    public function getJukeboxPathname(): ?string
    {
        return $this->musly->getCollection()->getJukeboxPathname();
    }

    public function hasTrack(SplFileInfo $track): bool
    {
        try {
            $tracks = $this->getTracks();
        } catch (SimilarTracksCollectionException) {
            return false;
        }

        $paths = array_map(fn (array $muslyTrack): string => $muslyTrack['track-origin'], $tracks);

        return in_array($track->getPathname(), $paths);
    }
}
