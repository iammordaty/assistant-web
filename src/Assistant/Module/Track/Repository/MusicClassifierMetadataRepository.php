<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Track\Model\MusicClassifierMetadataDto;
use MongoDB\Database;

/** Repozytorium metadanych z klasyfikatora audio */
class MusicClassifierMetadataRepository
{
    private const string COLLECTION_NAME = 'music_classifier_metadata';

    private const string FIELD_TRACK_GUID = 'track_guid';

    public function __construct(private Storage $storage)
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

    /** Zwraca metadane utworu o podanym guidzie */
    public function getByTrackGuid(string $trackGuid): ?MusicClassifierMetadataDto
    {
        $document = $this->storage->findOneBy([ self::FIELD_TRACK_GUID => $trackGuid ]);

        if (!$document) {
            return null;
        }

        $musicClassifierMetadata = MusicClassifierMetadataDto::fromStorage($document);

        return $musicClassifierMetadata;
    }

    /**
     * Usuwa metadane utworów o podanych guidach
     *
     * @param string[] $trackGuids
     * @return int liczba usuniętych dokumentów
     */
    public function removeByTrackGuids(array $trackGuids): int
    {
        if (!$trackGuids) {
            return 0;
        }

        $removed = $this->storage->removeBy([
            self::FIELD_TRACK_GUID => [ '$in' => array_values($trackGuids) ],
        ]);

        return $removed;
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

    public function count(): int
    {
        return $this->storage->count();
    }
}
