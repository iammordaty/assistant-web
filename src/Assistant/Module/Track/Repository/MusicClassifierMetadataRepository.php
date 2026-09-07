<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Track\Model\MusicClassifierMetadataDto;
use MongoDB\Database;

/** Repozytorium metadanych z klasyfikatora audio */
final class MusicClassifierMetadataRepository
{
    private const string COLLECTION_NAME = 'music_classifier_metadata';

    private const string FIELD_TRACK_GUID = 'track_guid';

    private function __construct(private Storage $storage)
    {
    }

    public static function factory(Database $database): self
    {
        $collection = $database->selectCollection(self::COLLECTION_NAME);
        $storage = new Storage($collection);

        $repository = new self($storage);

        return $repository;
    }

    /**
     * Zapisuje metadane utworu.
     *
     * Storage nie udostępnia upserta, dlatego istnienie dokumentu jest sprawdzane przed zapisem.
     * Metoda nie zwraca informacji o modyfikacji, ponieważ zapis identycznych danych nie zmienia
     * dokumentu i byłby nieodróżnialny od niepowodzenia.
     */
    public function save(MusicClassifierMetadataDto $musicClassifierMetadata): void
    {
        $document = $this->storage->findOneBy([
            self::FIELD_TRACK_GUID => $musicClassifierMetadata->trackGuid,
        ]);

        if ($document) {
            $this->storage->updateById($document['_id'], $musicClassifierMetadata->toStorage());

            return;
        }

        $this->storage->insert($musicClassifierMetadata->toStorage());
    }

    /**
     * Zwraca guidy utworów, dla których metadane są już zapisane. Pobierane jednym zapytaniem,
     * żeby pominięcie gotowych utworów nie oznaczało zapytania na każdy utwór osobno.
     *
     * @return string[]
     */
    public function getTrackGuids(): array
    {
        $documents = $this->storage->findBy([], [
            'projection' => [ self::FIELD_TRACK_GUID => 1 ],
        ]);

        $trackGuids = [];

        foreach ($documents as $document) {
            $trackGuids[] = $document[self::FIELD_TRACK_GUID];
        }

        return $trackGuids;
    }
}
