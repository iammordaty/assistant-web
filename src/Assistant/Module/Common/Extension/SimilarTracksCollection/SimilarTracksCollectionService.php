<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

use Assistant\Module\Common\Extension\Config;
use Musly\Collection;
use Musly\Exception\RuntimeException;
use Musly\Musly;
use SplFileInfo;

final class SimilarTracksCollectionService
{
    private const COLLECTION_FILENAME = 'collection.musly';
    private const SIMILAR_TRACKS_LIMIT = 200; // @idea Zastanowić się nad zwiększeniem lub uelastycznieniem limitu
    private const WITH_TRACK_DISTANCE = '-o long';

    private Musly $musly;

    public function __construct(private Config $config)
    {
        $pathname = $this->config->get('collection.metadata_dirs.music_similarity') . '/' . self::COLLECTION_FILENAME;

        $collection = new Collection([
            'pathname' => $pathname,
            'jukeboxPathname' => Collection::USE_DEFAULT_JUKEBOX_PATHNAME,
        ]);

        $this->musly = new Musly([ 'collection' => $collection ]);
    }

    public function add(SplFileInfo $track): bool
    {
        try {
            $this->musly->analyze($track->getPathname());
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
}
