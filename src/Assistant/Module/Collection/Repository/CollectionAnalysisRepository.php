<?php

namespace Assistant\Module\Collection\Repository;

use Assistant\Module\Collection\Extension\Analysis\AnalysisIssue;
use Assistant\Module\Common\Storage\Storage;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;

final class CollectionAnalysisRepository
{
    private const string COLLECTION_NAME = 'collection_analysis';

    private function __construct(private Storage $storage)
    {
    }

    public static function factory(Database $database): self
    {
        return new self(
            new Storage($database->selectCollection(self::COLLECTION_NAME)),
        );
    }

    public function getSummary(): ?array
    {
        $document = $this->storage->findOneBy(['record_type' => 'summary']);

        if (!$document) {
            return null;
        }

        $data = $document->bsonSerialize();

        return [
            'created_at' => $data->created_at->toDateTime(),
            'tracks_in_db' => $data->tracks_in_db,
            'directories_count' => $data->directories_count,
            'genres_count' => $data->genres_count,
            'artists_count' => $data->artists_count,
        ];
    }

    /** @return AnalysisIssue[] */
    public function getAllIssues(): array
    {
        $documents = $this->storage->findBy(['record_type' => 'issue']);
        $issues = [];

        foreach ($documents as $document) {
            $issues[] = $this->documentToIssue($document);
        }

        return $issues;
    }

    /** @param AnalysisIssue[] $issues */
    public function saveAnalysis(array $summary, array $issues): void
    {
        $ignoredHashes = $this->getIgnoredHashes();

        $this->storage->removeBy([]);

        $this->storage->insert([
            'record_type' => 'summary',
            'created_at' => new UTCDateTime(),
            ...$summary,
        ]);

        foreach ($issues as $issue) {
            $data = $issue->toStorage();
            $data['ignored'] = in_array($issue->hash, $ignoredHashes, true);

            $this->storage->insert($data);
        }
    }

    public function toggleIgnore(string $hash): bool
    {
        $document = $this->storage->findOneBy([
            'record_type' => 'issue',
            'hash' => $hash,
        ]);

        if (!$document) {
            return false;
        }

        $currentlyIgnored = (bool) ($document->bsonSerialize()->ignored ?? false);
        $newValue = !$currentlyIgnored;

        $this->storage->update(
            ['record_type' => 'issue', 'hash' => $hash],
            ['ignored' => $newValue],
        );

        return $newValue;
    }

    /** @return string[] */
    private function getIgnoredHashes(): array
    {
        $documents = $this->storage->findBy([
            'record_type' => 'issue',
            'ignored' => true,
        ]);

        $hashes = [];

        foreach ($documents as $document) {
            $hashes[] = $document->bsonSerialize()->hash;
        }

        return $hashes;
    }

    private function documentToIssue(BSONDocument $document): AnalysisIssue
    {
        $data = $document->bsonSerialize();

        return AnalysisIssue::fromStorage((array) $data);
    }
}
